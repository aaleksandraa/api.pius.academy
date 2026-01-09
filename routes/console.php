<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\PushNotificationService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('push:test {title?} {body?}', function ($title = 'Test Notification', $body = 'This is a test push notification from PMU Akademija') {
    $pushService = app(PushNotificationService::class);
    $pushService->sendToAll($title, $body, ['type' => 'test']);
    $this->info('Push notification sent to all active tokens!');
})->purpose('Send a test push notification to all registered devices');
