<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkFeedback extends Model
{
    use HasFactory;

    protected $table = 'work_feedback';

    protected $fillable = [
        'work_id',
        'educator_id',
        'feedback_text',
        'audio_url',
    ];

    public function work(): BelongsTo
    {
        return $this->belongsTo(StudentWork::class, 'work_id');
    }

    public function educator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'educator_id');
    }
}
