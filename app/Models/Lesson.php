<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'vimeo_embed',
        'description',
        'order_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getVimeoIdAttribute(): ?string
    {
        if (preg_match('/player\.vimeo\.com\/video\/(\d+)/', $this->vimeo_embed, $matches)) {
            return $matches[1];
        }
        if (preg_match('/vimeo\.com\/video\/(\d+)|vimeo\.com\/(\d+)/', $this->vimeo_embed, $matches)) {
            return $matches[1] ?? $matches[2];
        }
        return null;
    }

    public function getVimeoHashAttribute(): ?string
    {
        if (preg_match('/[?&]h=([a-zA-Z0-9]+)/', $this->vimeo_embed, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        $vimeoId = $this->vimeo_id;
        return $vimeoId ? "https://vumbnail.com/{$vimeoId}.jpg" : null;
    }
}
