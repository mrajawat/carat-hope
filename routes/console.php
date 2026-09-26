<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Artisan::command('orders:expire-unpaid', function () {
    $minutes = (int) config('services.razorpay.order_expiry_minutes', 30);
    $service = app(\App\Services\RazorpayService::class);

    $orders = \App\Models\Order::where('payment_method', 'razorpay')
        ->where('payment_status', 'unpaid')
        ->where('order_status', 'pending')
        ->where('created_at', '<', now()->subMinutes($minutes))
        ->get();

    foreach ($orders as $order) {
        $service->cancelUnpaid($order);
    }

    $this->info("Cancelled {$orders->count()} unpaid order(s).");
})->purpose('Cancel unpaid Razorpay orders and restore stock');

Schedule::command('orders:expire-unpaid')->everyTenMinutes()->withoutOverlapping();
