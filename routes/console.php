<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fcm:test {token}', function ($token) {
    $this->info("Sending test notification to token: " . $token);
    
    $fcmService = app(\App\Services\FcmNotificationService::class);
    $result = $fcmService->sendToTokens(
        [$token],
        'Test Push Notification',
        'Hello! This is a test notification from your Laravel App.',
        ['test_key' => 'test_val']
    );
    
    $this->info("Result: " . json_encode($result));
})->purpose('Send a test FCM notification to a device token');
