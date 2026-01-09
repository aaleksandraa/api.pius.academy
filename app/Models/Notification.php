<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'from_user_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // Helper to create notifications
    public static function notify(int $userId, string $type, string $title, string $message, ?string $link = null, ?int $fromUserId = null): self
    {
        $notification = self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'from_user_id' => $fromUserId,
        ]);

        // Send push notification
        try {
            $pushService = new \App\Services\PushNotificationService();
            $pushService->sendToUser($userId, $title, $message, ['link' => $link ?? '']);
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
        }

        return $notification;
    }

    // Notify all users (for admin announcements)
    public static function notifyAll(string $type, string $title, string $message, ?string $link = null, ?int $fromUserId = null, ?int $exceptUserId = null): void
    {
        $users = User::when($exceptUserId, fn($q) => $q->where('id', '!=', $exceptUserId))->get();
        
        foreach ($users as $user) {
            self::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'from_user_id' => $fromUserId,
            ]);
        }

        // Send push notification to all
        try {
            $pushService = new \App\Services\PushNotificationService();
            $pushService->sendToAll($title, $message, ['link' => $link ?? '']);
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
        }
    }
}
