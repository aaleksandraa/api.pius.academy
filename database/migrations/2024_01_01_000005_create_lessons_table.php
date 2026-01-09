<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('vimeo_embed');
            $table->text('description')->nullable();
            $table->integer('order_number')->default(1);
            $table->timestamps();

            $table->index(['course_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
