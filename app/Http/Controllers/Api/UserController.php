<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Notification;
use App\Models\Question;
use App\Models\QuestionAnswer;
use App\Models\StudentWork;
use App\Models\TestResult;
use App\Models\User;
use App\Models\WorkFeedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::with(['roles', 'educator'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'users' => $users->map(fn($u) => $this->formatUser($u)),
            'stats' => [
                'admins' => $users->filter(fn($u) => $u->hasRole('admin'))->count(),
                'educators' => $users->filter(fn($u) => $u->hasRole('educator'))->count(),
                'students' => $users->filter(fn($u) => $u->hasRole('student'))->count(),
            ],
        ]);
    }

    public function educators(): JsonResponse
    {
        $educators = User::role('educator')->get();

        return response()->json([
            'educators' => $educators->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
            ]),
        ]);
    }

    public function list(): JsonResponse
    {
        $users = User::with('roles')
            ->orderBy('name')
            ->get();

        return response()->json([
            'users' => $users->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->roles->first()?->name ?? 'student',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'role' => 'required|in:admin,educator,student',
            'educator_id' => 'nullable|exists:users,id',
        ]);


        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'educator_id' => $validated['educator_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return response()->json([
            'message' => 'Korisnik je uspješno kreiran.',
            'user' => $this->formatUser($user),
            'credentials' => [
                'email' => $validated['email'],
                'password' => $validated['password'],
            ],
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'role' => 'required|in:admin,educator,student',
            'educator_id' => 'nullable|exists:users,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'educator_id' => $validated['educator_id'] ?? null,
        ]);

        $user->syncRoles([$validated['role']]);

        return response()->json([
            'message' => 'Korisnik je uspješno ažuriran.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Korisnik je uspješno obrisan.',
        ]);
    }

    /**
     * Clear all user-generated content (posts, questions, works, comments, test results, notifications)
     * Does NOT delete: users, courses, lessons, tests, materials, zoom recordings
     */
    public function clearAllContent(): JsonResponse
    {
        DB::transaction(function () {
            // Delete in correct order due to foreign key constraints
            
            // 1. Delete all notifications
            Notification::truncate();
            
            // 2. Delete all work feedback
            WorkFeedback::truncate();
            
            // 3. Delete all student works
            StudentWork::truncate();
            
            // 4. Delete all question answers
            QuestionAnswer::truncate();
            
            // 5. Delete all questions
            Question::truncate();
            
            // 6. Delete all feed comments
            FeedComment::truncate();
            
            // 7. Delete all feed posts
            FeedPost::truncate();
            
            // 8. Delete all test results
            TestResult::truncate();
        });

        return response()->json([
            'message' => 'Sav korisnički sadržaj je uspješno obrisan (objave, pitanja, radovi, komentari, rezultati testova, notifikacije).',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->roles->first()?->name ?? 'student',
            'educator_id' => $user->educator_id,
            'educator' => $user->educator ? [
                'id' => $user->educator->id,
                'name' => $user->educator->name,
            ] : null,
            'created_at' => $user->created_at,
        ];
    }
}
