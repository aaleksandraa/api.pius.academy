<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentWork;
use App\Models\WorkFeedback;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentWorkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StudentWork::with(['student', 'feedback.educator'])
            ->orderByDesc('created_at');

        $works = $query->paginate(10);

        return response()->json([
            'works' => $works->getCollection()->map(fn($w) => $this->formatWork($w)),
            'meta' => [
                'current_page' => $works->currentPage(),
                'last_page' => $works->lastPage(),
                'total' => $works->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:10240',
            'file' => 'nullable|file|max:20480',
        ]);

        $imageUrl = null;
        $fileUrl = null;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('student-works', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('student-works-files', 'public');
            $fileUrl = Storage::disk('public')->url($path);
        }

        // Create feed post first
        $feedPost = FeedPost::create([
            'author_id' => $request->user()->id,
            'content' => ($validated['title'] ?? '') . ($validated['description'] ? "\n\n" . $validated['description'] : ''),
            'post_type' => 'work',
            'image_url' => $imageUrl,
        ]);

        $work = StudentWork::create([
            'student_id' => $request->user()->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image_url' => $imageUrl,
            'file_url' => $fileUrl,
            'feed_post_id' => $feedPost->id,
        ]);

        $work->load('student');

        return response()->json([
            'message' => 'Rad je uspješno dodan.',
            'work' => $this->formatWork($work),
        ], 201);
    }

    public function update(Request $request, StudentWork $studentWork): JsonResponse
    {
        $this->authorize('update', $studentWork);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $studentWork->update($validated);

        return response()->json([
            'message' => 'Rad je uspješno ažuriran.',
            'work' => $this->formatWork($studentWork),
        ]);
    }

    public function destroy(StudentWork $studentWork): JsonResponse
    {
        $this->authorize('delete', $studentWork);
        
        // Delete associated feed post if exists
        if ($studentWork->feed_post_id) {
            FeedPost::where('id', $studentWork->feed_post_id)->delete();
        }
        
        $studentWork->delete();

        return response()->json(['message' => 'Rad je uspješno obrisan.']);
    }

    public function feedback(StudentWork $studentWork): JsonResponse
    {
        $feedback = $studentWork->feedback()->with('educator')->get();

        return response()->json([
            'feedback' => $feedback->map(fn($f) => $this->formatFeedback($f)),
        ]);
    }

    public function storeFeedback(Request $request, StudentWork $studentWork): JsonResponse
    {
        $validated = $request->validate([
            'feedback_text' => 'nullable|string',
            'audio' => 'nullable|file|mimes:webm,mp3,wav|max:10240',
        ]);

        $audioUrl = null;
        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->store('work-feedback-audio', 'public');
            $audioUrl = Storage::disk('public')->url($path);
        }

        $feedback = $studentWork->feedback()->create([
            'educator_id' => $request->user()->id,
            'feedback_text' => $validated['feedback_text'] ?? '',
            'audio_url' => $audioUrl,
        ]);

        $feedback->load('educator');

        // Notify work author about new feedback
        if ($studentWork->student_id !== $request->user()->id) {
            Notification::notify(
                $studentWork->student_id,
                'work_feedback',
                'Novi komentar na vaš rad',
                $request->user()->name . ' je komentarisao vaš rad "' . $studentWork->title . '"',
                '/works',
                $request->user()->id
            );
        }

        // Check for @mentions
        if (!empty($validated['feedback_text'])) {
            $this->notifyMentionedUsers($validated['feedback_text'], $request->user(), '/works');
        }

        return response()->json([
            'message' => 'Komentar je uspješno dodan.',
            'feedback' => $this->formatFeedback($feedback),
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

    public function updateFeedback(Request $request, WorkFeedback $workFeedback): JsonResponse
    {
        $this->authorize('update', $workFeedback);

        $validated = $request->validate([
            'feedback_text' => 'required|string',
        ]);

        $workFeedback->update($validated);

        return response()->json([
            'message' => 'Komentar je uspješno ažuriran.',
            'feedback' => $this->formatFeedback($workFeedback),
        ]);
    }

    public function destroyFeedback(WorkFeedback $workFeedback): JsonResponse
    {
        $this->authorize('delete', $workFeedback);
        $workFeedback->delete();

        return response()->json(['message' => 'Komentar je uspješno obrisan.']);
    }

    private function formatWork(StudentWork $work): array
    {
        return [
            'id' => $work->id,
            'student_id' => $work->student_id,
            'student' => $work->student ? [
                'id' => $work->student->id,
                'name' => $work->student->name,
            ] : null,
            'title' => $work->title,
            'description' => $work->description,
            'image_url' => $work->image_url,
            'file_url' => $work->file_url,
            'feedback_count' => $work->feedback->count(),
            'created_at' => $work->created_at,
            'updated_at' => $work->updated_at,
        ];
    }

    private function formatFeedback(WorkFeedback $feedback): array
    {
        return [
            'id' => $feedback->id,
            'work_id' => $feedback->work_id,
            'educator_id' => $feedback->educator_id,
            'educator' => $feedback->educator ? [
                'id' => $feedback->educator->id,
                'name' => $feedback->educator->name,
                'role' => $feedback->educator->roles->first()?->name ?? 'student',
            ] : null,
            'feedback_text' => $feedback->feedback_text,
            'audio_url' => $feedback->audio_url,
            'created_at' => $feedback->created_at,
        ];
    }
}

