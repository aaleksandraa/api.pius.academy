<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Question;
use App\Models\QuestionAnswer;
use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class QuestionController extends Controller
{
    public function index(): JsonResponse
    {
        $questions = Question::with(['student', 'answers.educator'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'questions' => $questions->getCollection()->map(fn($q) => $this->formatQuestion($q)),
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'total' => $questions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'image' => 'nullable|image|max:10240',
        ]);

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('questions', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        // Create feed post first
        $feedPost = FeedPost::create([
            'author_id' => $request->user()->id,
            'content' => $validated['question_text'],
            'post_type' => 'question',
            'image_url' => $imageUrl,
        ]);

        $question = Question::create([
            'student_id' => $request->user()->id,
            'question_text' => $validated['question_text'],
            'image_url' => $imageUrl,
            'feed_post_id' => $feedPost->id,
        ]);

        $question->load('student');

        return response()->json([
            'message' => 'Pitanje je uspješno postavljeno.',
            'question' => $this->formatQuestion($question),
        ], 201);
    }


    public function update(Request $request, Question $question): JsonResponse
    {
        $this->authorize('update', $question);

        $validated = $request->validate([
            'question_text' => 'required|string',
        ]);

        $question->update($validated);

        return response()->json([
            'message' => 'Pitanje je uspješno ažurirano.',
            'question' => $this->formatQuestion($question),
        ]);
    }

    public function destroy(Question $question): JsonResponse
    {
        $this->authorize('delete', $question);
        
        // Delete associated feed post if exists
        if ($question->feed_post_id) {
            $feedPost = FeedPost::find($question->feed_post_id);
            if ($feedPost) {
                $feedPost->comments()->delete();
                $feedPost->delete();
            }
        }
        
        // Delete all answers
        $question->answers()->delete();
        
        // Delete the question
        $question->delete();

        return response()->json(['message' => 'Pitanje je uspješno obrisano.']);
    }

    public function answers(Question $question): JsonResponse
    {
        $answers = $question->answers()->with('educator')->get();

        return response()->json([
            'answers' => $answers->map(fn($a) => $this->formatAnswer($a)),
        ]);
    }

    public function storeAnswer(Request $request, Question $question): JsonResponse
    {
        $validated = $request->validate([
            'answer_text' => 'nullable|string',
            'audio' => 'nullable|file|mimes:webm,mp3,wav|max:10240',
        ]);

        $audioUrl = null;
        if ($request->hasFile('audio')) {
            $path = $request->file('audio')->store('question-audio', 'public');
            $audioUrl = Storage::disk('public')->url($path);
        }

        $answer = $question->answers()->create([
            'educator_id' => $request->user()->id,
            'answer_text' => $validated['answer_text'] ?? '',
            'audio_url' => $audioUrl,
        ]);

        $question->update(['is_answered' => true]);
        $answer->load('educator');

        // Notify question author about new answer
        if ($question->student_id !== $request->user()->id) {
            Notification::notify(
                $question->student_id,
                'question_answer',
                'Odgovor na vaše pitanje',
                $request->user()->name . ' je odgovorio na vaše pitanje',
                '/questions',
                $request->user()->id
            );
        }

        // Check for @mentions
        if (!empty($validated['answer_text'])) {
            $this->notifyMentionedUsers($validated['answer_text'], $request->user(), '/questions');
        }

        return response()->json([
            'message' => 'Odgovor je uspješno dodan.',
            'answer' => $this->formatAnswer($answer),
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
                        $fromUser->name . ' vas je spomenuo u odgovoru',
                        $link,
                        $fromUser->id
                    );
                }
            }
        }
    }

    public function updateAnswer(Request $request, QuestionAnswer $questionAnswer): JsonResponse
    {
        $this->authorize('update', $questionAnswer);

        $validated = $request->validate([
            'answer_text' => 'required|string',
        ]);

        $questionAnswer->update($validated);

        return response()->json([
            'message' => 'Odgovor je uspješno ažuriran.',
            'answer' => $this->formatAnswer($questionAnswer),
        ]);
    }

    public function destroyAnswer(QuestionAnswer $questionAnswer): JsonResponse
    {
        $this->authorize('delete', $questionAnswer);
        $questionAnswer->delete();

        return response()->json(['message' => 'Odgovor je uspješno obrisan.']);
    }

    private function formatQuestion(Question $question): array
    {
        return [
            'id' => $question->id,
            'student_id' => $question->student_id,
            'student' => $question->student ? [
                'id' => $question->student->id,
                'name' => $question->student->name,
            ] : null,
            'question_text' => $question->question_text,
            'image_url' => $question->image_url,
            'is_answered' => $question->is_answered,
            'answers_count' => $question->answers->count(),
            'created_at' => $question->created_at,
        ];
    }

    private function formatAnswer(QuestionAnswer $answer): array
    {
        return [
            'id' => $answer->id,
            'question_id' => $answer->question_id,
            'educator_id' => $answer->educator_id,
            'educator' => $answer->educator ? [
                'id' => $answer->educator->id,
                'name' => $answer->educator->name,
            ] : null,
            'answer_text' => $answer->answer_text,
            'audio_url' => $answer->audio_url,
            'created_at' => $answer->created_at,
        ];
    }
}

