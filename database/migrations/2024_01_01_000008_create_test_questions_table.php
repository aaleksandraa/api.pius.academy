<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'text'])->default('multiple_choice');
            $table->string('correct_answer')->nullable();
            $table->json('options')->nullable();
            $table->integer('order_number')->default(1);
            $table->timestamps();

            $table->index(['test_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_questions');
    }
};
