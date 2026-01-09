<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index(): JsonResponse
    {
        $courses = Course::active()
            ->with(['lessons' => fn($q) => $q->active()->orderBy('order_number')])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'courses' => $courses->map(fn($course) => $this->formatCourse($course)),
        ]);
    }

    public function show(Course $course): JsonResponse
    {
        $course->load(['lessons' => fn($q) => $q->active()->orderBy('order_number')]);

        return response()->json([
            'course' => $this->formatCourse($course),
        ]);
    }

    public function lesson(Lesson $lesson): JsonResponse
    {
        return response()->json([
            'lesson' => $this->formatLesson($lesson),
        ]);
    }

    // Admin methods
    public function adminIndex(): JsonResponse
    {
        $courses = Course::with(['lessons' => fn($q) => $q->orderBy('order_number')])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'courses' => $courses->map(fn($course) => $this->formatCourse($course)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $course = Course::create($validated);

        // Notify all users if course is active
        if ($course->is_active) {
            Notification::notifyAll(
                'new_course',
                'Novi kurs',
                'Kurs "' . $course->title . '" je sada dostupan.',
                '/course',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Kurs je uspješno kreiran.',
            'course' => $this->formatCourse($course),
        ], 201);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $wasActive = $course->is_active;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $course->update($validated);

        // Notify all users if course just became active
        if (!$wasActive && $course->is_active) {
            Notification::notifyAll(
                'new_course',
                'Novi kurs',
                'Kurs "' . $course->title . '" je sada dostupan.',
                '/course',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Kurs je uspješno ažuriran.',
            'course' => $this->formatCourse($course),
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json([
            'message' => 'Kurs je uspješno obrisan.',
        ]);
    }

    // Lesson management
    public function storeLesson(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'vimeo_embed' => 'required|string',
            'description' => 'nullable|string',
            'order_number' => 'required|integer|min:1',
        ]);

        $lesson = $course->lessons()->create($validated);

        // Notify all users if course is active
        if ($course->is_active) {
            Notification::notifyAll(
                'new_lesson',
                'Nova lekcija',
                'Nova lekcija "' . $lesson->title . '" je dodana u kurs "' . $course->title . '".',
                '/course',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Lekcija je uspješno kreirana.',
            'lesson' => $this->formatLesson($lesson),
        ], 201);
    }

    public function updateLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $wasActive = $lesson->is_active;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'vimeo_embed' => 'required|string',
            'description' => 'nullable|string',
            'order_number' => 'required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);

        $lesson->update($validated);

        // Notify all users if lesson just became active and course is active
        if (!$wasActive && $lesson->is_active && $lesson->course->is_active) {
            Notification::notifyAll(
                'new_lesson',
                'Nova lekcija',
                'Nova lekcija "' . $lesson->title . '" je dodana u kurs "' . $lesson->course->title . '".',
                '/course',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => 'Lekcija je uspješno ažurirana.',
            'lesson' => $this->formatLesson($lesson),
        ]);
    }

    public function toggleLessonActive(Request $request, Lesson $lesson): JsonResponse
    {
        $wasActive = $lesson->is_active;
        $lesson->is_active = !$lesson->is_active;
        $lesson->save();

        // Notify all users if lesson just became active and course is active
        if (!$wasActive && $lesson->is_active && $lesson->course->is_active) {
            Notification::notifyAll(
                'new_lesson',
                'Nova lekcija',
                'Nova lekcija "' . $lesson->title . '" je dodana u kurs "' . $lesson->course->title . '".',
                '/course',
                null,
                $request->user()->id
            );
        }

        return response()->json([
            'message' => $lesson->is_active ? 'Lekcija je sada aktivna.' : 'Lekcija je sada neaktivna.',
            'lesson' => $this->formatLesson($lesson),
        ]);
    }

    public function destroyLesson(Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json([
            'message' => 'Lekcija je uspješno obrisana.',
        ]);
    }

    private function formatCourse(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'description' => $course->description,
            'is_active' => $course->is_active,
            'lessons' => $course->lessons->map(fn($l) => $this->formatLesson($l)),
            'created_at' => $course->created_at,
        ];
    }

    private function formatLesson(Lesson $lesson): array
    {
        return [
            'id' => $lesson->id,
            'course_id' => $lesson->course_id,
            'title' => $lesson->title,
            'vimeo_embed' => $lesson->vimeo_embed,
            'vimeo_id' => $lesson->vimeo_id,
            'vimeo_hash' => $lesson->vimeo_hash,
            'thumbnail_url' => $lesson->thumbnail_url,
            'description' => $lesson->description,
            'order_number' => $lesson->order_number,
            'is_active' => $lesson->is_active,
        ];
    }
}

