<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedPost;
use App\Models\FeedComment;
use App\Models\Notification;
use App\Models\Question;
use App\Models\StudentWork;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FeedController extends Controller
{
    public function index(): JsonResponse
    {
        $posts = FeedPost::with(['author', 'comments'])
            ->ordered()
            ->paginate(20);

        return response()->json([
            'posts' => $posts->getCollection()->map(fn($p) => $this->formatPost($p)),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'nullable|string',
            'post_type' => 'required|in:question,work,status',
            'image' => 'nullable|image|max:10240',
            'title' => 'nullable|string|max:255', // For work posts
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('feed-images', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        $post = FeedPost::create([
            'author_id' => $request->user()->id,
            'content' => $validated['content'],
            'post_type' => $validated['post_type'],
            'image_url' => $imageUrl,
        ]);

        // Auto-create Question if post_type is 'question'
        if ($validated['post_type'] === 'question' && !empty($validated['content'])) {
            Question::create([
                'student_id' => $request->user()->id,
                'question_text' => $validated['content'],
                'image_url' => $imageUrl,
                'feed_post_id' => $post->id,
            ]);
        }

        // Auto-create StudentWork if post_type is 'work'
        if ($validated['post_type'] === 'work') {
            StudentWork::create([
                'student_id' => $request->user()->id,
                'title' => $validated['title'] ?? 'Rad sa početne',
                'description' => $validated['content'],
                'image_url' => $imageUrl,
                'feed_post_id' => $post->id,
            ]);
        }

        $post->load('author');

        // Send notification to all users if admin posts a status
        if ($validated['post_type'] === 'status' && $request->user()->hasRole('admin')) {
            Notification::notifyAll(
                'admin_announcement',
                'Nova obavijest',
                mb_substr(strip_tags($validated['content'] ?? ''), 0, 100) . '...',
                '/',
                $request->user()->id,
                $request->user()->id // except admin who posted
            );
        }

        return response()->json([
            'message' => 'Objava je uspješno kreirana.',
            'post' => $this->formatPost($post),
        ], 201);
    }

    public function update(Request $request, FeedPost $feedPost): JsonResponse
    {
        $this->authorize('update', $feedPost);

        $validated = $request->validate([
            'content' => 'nullable|string',
        ]);

        $feedPost->update($validated);

        return response()->json([
            'message' => 'Objava je uspješno ažurirana.',
            'post' => $this->formatPost($feedPost),
        ]);
    }

    public function destroy(FeedPost $feedPost): JsonResponse
    {
        $this->authorize('delete', $feedPost);

        // Delete associated question or work if exists
        Question::where('feed_post_id', $feedPost->id)->delete();
        StudentWork::where('feed_post_id', $feedPost->id)->delete();

        $feedPost->delete();

        return response()->json([
            'message' => 'Objava je uspješno obrisana.',
        ]);
    }

    public function togglePin(FeedPost $feedPost): JsonResponse
    {
        $this->authorize('pin', $feedPost);

        $feedPost->update(['is_pinned' => !$feedPost->is_pinned]);

        return response()->json([
            'message' => $feedPost->is_pinned ? 'Objava je zakačena.' : 'Objava je otkačena.',
            'post' => $this->formatPost($feedPost),
        ]);
    }

    // Comments
    public function comments(FeedPost $feedPost): JsonResponse
    {
        $comments = $feedPost->comments()->with('author')->get();

        return response()->json([
            'comments' => $comments->map(fn($c) => $this->formatComment($c)),
        ]);
    }

    public function storeComment(Request $request, FeedPost $feedPost): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'nullable|string',
            'audio' => 'nullable|file|mimes:webm,mp3,wav|max:10240',
        ]);

        $audioUrl = null;
        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->store('feed-audio', 'public');
            $audioUrl = Storage::disk('public')->url($path);
        }

        $comment = $feedPost->comments()->create([
            'author_id' => $request->user()->id,
            'content' => $validated['content'] ?? '',
            'audio_url' => $audioUrl,
        ]);

        $comment->load('author');

        // Notify post author about new comment (if not commenting on own post)
        if ($feedPost->author_id !== $request->user()->id) {
            Notification::notify(
                $feedPost->author_id,
                'comment_reply',
                'Novi komentar',
                $request->user()->name . ' je komentarisao vašu objavu',
                '/',
                $request->user()->id
            );
        }

        // Check for @mentions and notify mentioned users
        if (!empty($validated['content'])) {
            $this->notifyMentionedUsers($validated['content'], $request->user(), '/');
        }

        return response()->json([
            'message' => 'Komentar je uspješno dodan.',
            'comment' => $this->formatComment($comment),
        ], 201);
    }

    private function notifyMentionedUsers(string $content, $fromUser, string $link): void
    {
        preg_match_all('/@(\w+(?:\s+\w+)?)/', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $name) {
                $user = User::where('name', 'ILIKE', $name)->first();
                if ($user && $user->id !== $fromUser->id) {
                    Notification::notify(
                        $user->id,
                        'mention',
                        'Spomenuti ste',
                        $fromUser->name . ' vas je spomenuo u komentaru',
                        $link,
                        $fromUser->id
                    );
                }
            }
        }
    }

    public function updateComment(Request $request, FeedComment $feedComment): JsonResponse
    {
        $this->authorize('update', $feedComment);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $feedComment->update($validated);

        return response()->json([
            'message' => 'Komentar je uspješno ažuriran.',
            'comment' => $this->formatComment($feedComment),
        ]);
    }

    public function destroyComment(FeedComment $feedComment): JsonResponse
    {
        try {
            $this->authorize('delete', $feedComment);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'message' => 'Nemate dozvolu za brisanje ovog komentara.',
            ], 403);
        }

        $feedComment->delete();

        return response()->json([
            'message' => 'Komentar je uspješno obrisan.',
        ]);
    }

    private function formatPost(FeedPost $post): array
    {
        return [
            'id' => $post->id,
            'author_id' => $post->author_id,
            'author' => $post->author ? [
                'id' => $post->author->id,
                'name' => $post->author->name,
                'role' => $post->author->roles->first()?->name ?? 'student',
            ] : null,
            'content' => $post->content,
            'image_url' => $post->image_url,
            'post_type' => $post->post_type,
            'is_pinned' => $post->is_pinned,
            'comments_count' => $post->comments->count(),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ];
    }

    private function formatComment(FeedComment $comment): array
    {
        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'author_id' => $comment->author_id,
            'author' => $comment->author ? [
                'id' => $comment->author->id,
                'name' => $comment->author->name,
                'role' => $comment->author->roles->first()?->name ?? 'student',
            ] : null,
            'content' => $comment->content,
            'audio_url' => $comment->audio_url,
            'created_at' => $comment->created_at,
        ];
    }
}
