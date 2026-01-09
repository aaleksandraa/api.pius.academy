<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Models\FeedPost;
use App\Models\FeedComment;
use App\Models\Question;
use App\Models\QuestionAnswer;
use App\Models\StudentWork;
use App\Models\WorkFeedback;
use App\Policies\FeedPostPolicy;
use App\Policies\FeedCommentPolicy;
use App\Policies\QuestionPolicy;
use App\Policies\QuestionAnswerPolicy;
use App\Policies\StudentWorkPolicy;
use App\Policies\WorkFeedbackPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(FeedPost::class, FeedPostPolicy::class);
        Gate::policy(FeedComment::class, FeedCommentPolicy::class);
        Gate::policy(Question::class, QuestionPolicy::class);
        Gate::policy(QuestionAnswer::class, QuestionAnswerPolicy::class);
        Gate::policy(StudentWork::class, StudentWorkPolicy::class);
        Gate::policy(WorkFeedback::class, WorkFeedbackPolicy::class);
    }
}
