<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_id',
        'question_text',
        'question_type',
        'correct_answer',
        'options',
        'order_number',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'order_number' => 'integer',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function isCorrect(string $answer): bool
    {
        if ($this->question_type === 'text') {
            return true; // Text answers need manual review
        }
        return strtolower(trim($answer)) === strtolower(trim($this->correct_answer ?? ''));
    }
}
