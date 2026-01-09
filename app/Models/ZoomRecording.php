<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ZoomRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'vimeo_embed',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
        ];
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
}
