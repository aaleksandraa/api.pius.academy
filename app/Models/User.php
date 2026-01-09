<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'educator_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function educator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'educator_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'educator_id');
    }

    public function feedPosts(): HasMany
    {
        return $this->hasMany(FeedPost::class, 'author_id');
    }

    public function feedComments(): HasMany
    {
        return $this->hasMany(FeedComment::class, 'author_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'student_id');
    }

    public function questionAnswers(): HasMany
    {
        return $this->hasMany(QuestionAnswer::class, 'educator_id');
    }

    public function studentWorks(): HasMany
    {
        return $this->hasMany(StudentWork::class, 'student_id');
    }

    public function workFeedback(): HasMany
    {
        return $this->hasMany(WorkFeedback::class, 'educator_id');
    }

    public function testResults(): HasMany
    {
        return $this->hasMany(TestResult::class);
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isEducator(): bool
    {
        return $this->hasRole('educator');
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }
}
