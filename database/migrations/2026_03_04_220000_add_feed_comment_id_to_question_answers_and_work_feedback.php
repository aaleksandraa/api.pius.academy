<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_answers', function (Blueprint $table) {
            $table->foreignId('feed_comment_id')->nullable()->after('educator_id');
            $table->foreign('feed_comment_id', 'qa_feed_comment_fk')
                ->references('id')
                ->on('feed_comments')
                ->cascadeOnDelete();
            $table->unique('feed_comment_id', 'qa_feed_comment_unique');
        });

        Schema::table('work_feedback', function (Blueprint $table) {
            $table->foreignId('feed_comment_id')->nullable()->after('educator_id');
            $table->foreign('feed_comment_id', 'wf_feed_comment_fk')
                ->references('id')
                ->on('feed_comments')
                ->cascadeOnDelete();
            $table->unique('feed_comment_id', 'wf_feed_comment_unique');
        });

        $this->backfillQuestionAnswersFromFeedComments();
        $this->backfillWorkFeedbackFromFeedComments();
    }

    public function down(): void
    {
        Schema::table('question_answers', function (Blueprint $table) {
            $table->dropUnique('qa_feed_comment_unique');
            $table->dropForeign('qa_feed_comment_fk');
            $table->dropColumn('feed_comment_id');
        });

        Schema::table('work_feedback', function (Blueprint $table) {
            $table->dropUnique('wf_feed_comment_unique');
            $table->dropForeign('wf_feed_comment_fk');
            $table->dropColumn('feed_comment_id');
        });
    }

    private function backfillQuestionAnswersFromFeedComments(): void
    {
        $rows = DB::table('feed_comments as fc')
            ->join('feed_posts as fp', 'fp.id', '=', 'fc.post_id')
            ->join('questions as q', 'q.feed_post_id', '=', 'fp.id')
            ->where('fp.post_type', 'question')
            ->orderBy('fc.id')
            ->select([
                'fc.id as feed_comment_id',
                'q.id as question_id',
                'fc.author_id as educator_id',
                'fc.content as answer_text',
                'fc.audio_url',
                'fc.created_at',
                'fc.updated_at',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('question_answers')->insertOrIgnore(
            $rows->map(fn($row) => [
                'question_id' => $row->question_id,
                'educator_id' => $row->educator_id,
                'feed_comment_id' => $row->feed_comment_id,
                'answer_text' => $row->answer_text ?? '',
                'audio_url' => $row->audio_url,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all()
        );

        DB::table('questions')
            ->whereIn('id', $rows->pluck('question_id')->unique()->values()->all())
            ->update(['is_answered' => true]);
    }

    private function backfillWorkFeedbackFromFeedComments(): void
    {
        $rows = DB::table('feed_comments as fc')
            ->join('feed_posts as fp', 'fp.id', '=', 'fc.post_id')
            ->join('student_works as sw', 'sw.feed_post_id', '=', 'fp.id')
            ->where('fp.post_type', 'work')
            ->orderBy('fc.id')
            ->select([
                'fc.id as feed_comment_id',
                'sw.id as work_id',
                'fc.author_id as educator_id',
                'fc.content as feedback_text',
                'fc.audio_url',
                'fc.created_at',
                'fc.updated_at',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        DB::table('work_feedback')->insertOrIgnore(
            $rows->map(fn($row) => [
                'work_id' => $row->work_id,
                'educator_id' => $row->educator_id,
                'feed_comment_id' => $row->feed_comment_id,
                'feedback_text' => $row->feedback_text ?? '',
                'audio_url' => $row->audio_url,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all()
        );
    }
};
