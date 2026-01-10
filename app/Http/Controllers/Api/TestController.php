<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Test;
use App\Models\TestQuestion;
use App\Models\TestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(): JsonResponse
    {
        $tests = Test::active()->orderByDesc('created_at')->get();

        return response()->json([
            'tests' => $tests->map(fn($t) => $this->formatTest($t)),
        ]);
    }

    public function show(Test $test): JsonResponse
    {
        $test->load('questions');

        return response()->json([
            'test' => $this->formatTest($test, true),
        ]);
    }

    public function submit(Request $request, Test $test): JsonResponse
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'nullable|string',
        ]);

        $test->load('questions');
        $user = $request->user();
        $answers = $request->answers;

        $score = 0;
        $resultAnswers = [];

        foreach ($test->questions as $question) {
            $selectedAnswer = $answers[$question->id] ?? '';
            $isCorrect = $question->isCorrect($selectedAnswer);

            if ($isCorrect) $score++;

            $resultAnswers[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'selected_answer' => $selectedAnswer,
                'correct_answer' => $question->correct_answer ?? '',
                'is_correct' => $isCorrect,
            ];
        }

        $result = TestResult::create([
            'test_id' => $test->id,
            'user_id' => $user->id,
            'answers' => $resultAnswers,
            'score' => $score,
            'total_questions' => $test->questions->count(),
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Test je uspješno predat.',
            'result' => $this->formatResult($result),
        ]);
    }

    public function results(Request $request): JsonResponse
    {
        $results = TestResult::with(['test'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('completed_at')
            ->get();

        return response()->json([
            'results' => $results->map(fn($r) => $this->formatResult($r)),
        ]);
    }

    public function result(TestResult $testResult): JsonResponse
    {
        $this->authorize('view', $testResult);

        return response()->json([
            'result' => $this->formatResult($testResult),
        ]);
    }

    // Admin methods
    public function adminIndex(): JsonResponse
    {
        $tests = Test::with('questions')->orderByDesc('created_at')->get();

        return response()->json([
            'tests' => $tests->map(fn($t) => $this->formatTest($t, true)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $test = Test::create($validated);

        // Notify all users if test is active
        if ($test->is_active) {
            Notification::notifyAll(
                'new_test',
                'Novi test',
                'Test "' . $test->title . '" je sada dostupan.',
                '/tests',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Test je uspješno kreiran.',
            'test' => $this->formatTest($test),
        ], 201);
    }

    public function update(Request $request, Test $test): JsonResponse
    {
        $wasActive = $test->is_active;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $test->update($validated);

        // Notify all users if test just became active
        if (!$wasActive && $test->is_active) {
            Notification::notifyAll(
                'new_test',
                'Novi test',
                'Test "' . $test->title . '" je sada dostupan.',
                '/tests',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Test je uspješno ažuriran.',
            'test' => $this->formatTest($test),
        ]);
    }

    public function destroy(Test $test): JsonResponse
    {
        $test->delete();

        return response()->json([
            'message' => 'Test je uspješno obrisan.',
        ]);
    }

    // Question management
    public function storeQuestion(Request $request, Test $test): JsonResponse
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,true_false,text',
            'correct_answer' => 'nullable|string',
            'options' => 'nullable|array',
            'order_number' => 'required|integer|min:1',
        ]);

        $question = $test->questions()->create($validated);

        return response()->json([
            'message' => 'Pitanje je uspješno kreirano.',
            'question' => $this->formatQuestion($question),
        ], 201);
    }

    public function updateQuestion(Request $request, TestQuestion $testQuestion): JsonResponse
    {
        $validated = $request->validate([
            'question_text' => 'required|string',
            'question_type' => 'required|in:multiple_choice,true_false,text',
            'correct_answer' => 'nullable|string',
            'options' => 'nullable|array',
            'order_number' => 'required|integer|min:1',
        ]);

        $testQuestion->update($validated);

        return response()->json([
            'message' => 'Pitanje je uspješno ažurirano.',
            'question' => $this->formatQuestion($testQuestion),
        ]);
    }

    public function destroyQuestion(TestQuestion $testQuestion): JsonResponse
    {
        $testQuestion->delete();

        return response()->json([
            'message' => 'Pitanje je uspješno obrisano.',
        ]);
    }

    // Admin results
    public function allResults(): JsonResponse
    {
        $results = TestResult::with(['test', 'user'])
            ->orderByDesc('completed_at')
            ->get();

        return response()->json([
            'results' => $results->map(fn($r) => $this->formatResult($r, true)),
        ]);
    }

    // Educator results - same as admin
    public function educatorResults(): JsonResponse
    {
        return $this->allResults();
    }

    private function formatTest(Test $test, bool $withQuestions = false): array
    {
        $data = [
            'id' => $test->id,
            'title' => $test->title,
            'description' => $test->description,
            'is_active' => $test->is_active,
            'questions_count' => $test->questions_count ?? $test->questions->count(),
            'created_at' => $test->created_at,
        ];

        if ($withQuestions) {
            $data['questions'] = $test->questions->map(fn($q) => $this->formatQuestion($q));
        }

        return $data;
    }

    private function formatQuestion(TestQuestion $question): array
    {
        return [
            'id' => $question->id,
            'test_id' => $question->test_id,
            'question_text' => $question->question_text,
            'question_type' => $question->question_type,
            'correct_answer' => $question->correct_answer,
            'options' => $question->options,
            'order_number' => $question->order_number,
        ];
    }

    private function formatResult(TestResult $result, bool $withUser = false): array
    {
        $data = [
            'id' => $result->id,
            'test_id' => $result->test_id,
            'test' => $result->test ? [
                'id' => $result->test->id,
                'title' => $result->test->title,
            ] : null,
            'answers' => $result->answers,
            'score' => $result->score,
            'total_questions' => $result->total_questions,
            'percentage' => $result->percentage,
            'passed' => $result->passed,
            'completed_at' => $result->completed_at,
        ];

        if ($withUser && $result->user) {
            $data['user'] = [
                'id' => $result->user->id,
                'name' => $result->user->name,
                'email' => $result->user->email,
            ];
        }

        return $data;
    }
}
