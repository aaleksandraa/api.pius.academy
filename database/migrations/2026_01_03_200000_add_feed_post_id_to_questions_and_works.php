<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('feed_post_id')->nullable()->after('is_answered')->constrained('feed_posts')->nullOnDelete();
        });

        Schema::table('student_works', function (Blueprint $table) {
            $table->foreignId('feed_post_id')->nullable()->after('file_url')->constrained('feed_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign(['feed_post_id']);
            $table->dropColumn('feed_post_id');
        });

        Schema::table('student_works', function (Blueprint $table) {
            $table->dropForeign(['feed_post_id']);
            $table->dropColumn('feed_post_id');
        });
    }
};
