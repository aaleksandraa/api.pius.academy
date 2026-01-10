<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PushNotificationController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\StudentWorkController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\ZoomRecordingController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/password', [AuthController::class, 'updatePassword']);

    // Push Notifications
    Route::post('/push-token', [PushNotificationController::class, 'storeToken']);
    Route::delete('/push-token', [PushNotificationController::class, 'removeToken']);

    // Users list for mentions
    Route::get('/users/list', [UserController::class, 'list']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    // Courses & Lessons
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::get('/lessons/{lesson}', [CourseController::class, 'lesson']);

    // Zoom Recordings
    Route::get('/zoom-recordings', [ZoomRecordingController::class, 'index']);
    Route::get('/zoom-recordings/{zoomRecording}', [ZoomRecordingController::class, 'show']);

    // Tests
    Route::get('/tests', [TestController::class, 'index']);
    Route::get('/tests/{test}', [TestController::class, 'show']);
    Route::post('/tests/{test}/submit', [TestController::class, 'submit']);
    Route::get('/test-results', [TestController::class, 'results']);
    Route::get('/test-results/{testResult}', [TestController::class, 'result']);

    // Feed
    Route::get('/feed', [FeedController::class, 'index']);
    Route::post('/feed', [FeedController::class, 'store']);
    Route::put('/feed/{feedPost}', [FeedController::class, 'update']);
    Route::delete('/feed/{feedPost}', [FeedController::class, 'destroy']);
    Route::post('/feed/{feedPost}/pin', [FeedController::class, 'togglePin']);
    Route::get('/feed/{feedPost}/comments', [FeedController::class, 'comments']);
    Route::post('/feed/{feedPost}/comments', [FeedController::class, 'storeComment']);
    Route::put('/comments/{feedComment}', [FeedController::class, 'updateComment']);
    Route::delete('/comments/{feedComment}', [FeedController::class, 'destroyComment']);

    // Questions
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::post('/questions', [QuestionController::class, 'store']);
    Route::put('/questions/{question}', [QuestionController::class, 'update']);
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);
    Route::get('/questions/{question}/answers', [QuestionController::class, 'answers']);
    Route::post('/questions/{question}/answers', [QuestionController::class, 'storeAnswer']);
    Route::put('/question-answers/{questionAnswer}', [QuestionController::class, 'updateAnswer']);
    Route::delete('/question-answers/{questionAnswer}', [QuestionController::class, 'destroyAnswer']);

    // Student Works
    Route::get('/works', [StudentWorkController::class, 'index']);
    Route::post('/works', [StudentWorkController::class, 'store']);
    Route::put('/works/{studentWork}', [StudentWorkController::class, 'update']);
    Route::delete('/works/{studentWork}', [StudentWorkController::class, 'destroy']);
    Route::get('/works/{studentWork}/feedback', [StudentWorkController::class, 'feedback']);
    Route::post('/works/{studentWork}/feedback', [StudentWorkController::class, 'storeFeedback']);
    Route::put('/work-feedback/{workFeedback}', [StudentWorkController::class, 'updateFeedback']);
    Route::delete('/work-feedback/{workFeedback}', [StudentWorkController::class, 'destroyFeedback']);

    // Materials
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::get('/materials/check-enabled', [MaterialController::class, 'checkEnabled']);

    // Educator routes - can view test results
    Route::middleware('role:admin,educator')->group(function () {
        Route::get('/educator/test-results', [TestController::class, 'educatorResults']);
    });

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/educators', [UserController::class, 'educators']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/clear-content', [UserController::class, 'clearAllContent']);

        // Courses
        Route::get('/courses', [CourseController::class, 'adminIndex']);
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::post('/courses/{course}/lessons', [CourseController::class, 'storeLesson']);
        Route::put('/lessons/{lesson}', [CourseController::class, 'updateLesson']);
        Route::post('/lessons/{lesson}/toggle-active', [CourseController::class, 'toggleLessonActive']);
        Route::delete('/lessons/{lesson}', [CourseController::class, 'destroyLesson']);

        // Zoom Recordings
        Route::post('/zoom-recordings', [ZoomRecordingController::class, 'store']);
        Route::put('/zoom-recordings/{zoomRecording}', [ZoomRecordingController::class, 'update']);
        Route::delete('/zoom-recordings/{zoomRecording}', [ZoomRecordingController::class, 'destroy']);

        // Tests
        Route::get('/tests', [TestController::class, 'adminIndex']);
        Route::post('/tests', [TestController::class, 'store']);
        Route::put('/tests/{test}', [TestController::class, 'update']);
        Route::delete('/tests/{test}', [TestController::class, 'destroy']);
        Route::post('/tests/{test}/questions', [TestController::class, 'storeQuestion']);
        Route::put('/test-questions/{testQuestion}', [TestController::class, 'updateQuestion']);
        Route::delete('/test-questions/{testQuestion}', [TestController::class, 'destroyQuestion']);
        Route::get('/test-results', [TestController::class, 'allResults']);

        // Materials
        Route::get('/materials', [MaterialController::class, 'adminIndex']);
        Route::post('/materials', [MaterialController::class, 'store']);
        Route::put('/materials/{material}', [MaterialController::class, 'update']);
        Route::delete('/materials/{material}', [MaterialController::class, 'destroy']);
        Route::post('/materials/toggle-enabled', [MaterialController::class, 'toggleEnabled']);
        Route::post('/materials/reorder', [MaterialController::class, 'reorder']);
    });
});
