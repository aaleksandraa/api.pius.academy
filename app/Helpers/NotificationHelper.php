<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;
use App\Services\PushNotificationService;

class NotificationHelper
{
    /**
     * Create notification for all users and send push
     */
    public static function notifyAllUsers(
        string $type,
        string $title,
        string $message,
        string $link,
        ?int $fromUserId = null
    ): void {
        $users = User::all();
        
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'from_user_id' => $fromUserId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
            ]);
        }

        // Send push notification to all
        try {
            $pushService = new PushNotificationService();
            $pushService->sendToAll($title, $message, ['link' => $link]);
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create notification for specific user and send push
     */
    public static function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        string $link,
        ?int $fromUserId = null
    ): void {
        Notification::create([
            'user_id' => $userId,
            'from_user_id' => $fromUserId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);

        // Send push notification
        try {
            $pushService = new PushNotificationService();
            $pushService->sendToUser($userId, $title, $message, ['link' => $link]);
        } catch (\Exception $e) {
            \Log::error('Push notification failed: ' . $e->getMessage());
        }
    }
}
