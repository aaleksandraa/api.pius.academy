<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_id')->constrained('student_works')->cascadeOnDelete();
            $table->foreignId('educator_id')->constrained('users')->cascadeOnDelete();
            $table->text('feedback_text')->nullable();
            $table->string('audio_url')->nullable();
            $table->timestamps();

            $table->index('work_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_feedback');
    }
};
