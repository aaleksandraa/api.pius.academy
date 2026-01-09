<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'question_text',
        'image_url',
        'is_answered',
        'feed_post_id',
    ];

    protected function casts(): array
    {
        return [
            'is_answered' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function feedPost(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class)->orderBy('created_at');
    }
}
