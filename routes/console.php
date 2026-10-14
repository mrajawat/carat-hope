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

Artisan::command('payments:reconcile {--limit=100 : Maximum orders to check per run}', function () {
    $service = app(\App\Services\RazorpayService::class);

    // Give the frontend verify call and the webhook a few minutes to report first.
    // After a day nobody is coming back to the order, so it is no longer checked.
    $orders = \App\Models\Order::where('payment_method', 'razorpay')
        ->where('payment_status', 'unpaid')
        ->whereNotNull('razorpay_order_id')
        ->whereBetween('created_at', [now()->subDay(), now()->subMinutes(10)])
        ->orderByDesc('id')
        ->limit((int) $this->option('limit'))
        ->get();

    foreach ($orders as $order) {
        try {
            $outcome = $service->reconcile($order);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Payment reconcile failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            $this->error("Order {$order->id}: {$e->getMessage()}");
            continue;
        }

        $this->line("Order {$order->id}: {$outcome}");
    }
})->purpose('Settle Razorpay orders whose result never reached the app (missed verify call and webhook)');

// Reconcile runs first so a paid-but-unreported order is settled before it can be expired.
Schedule::command('payments:reconcile')->everyTenMinutes()->withoutOverlapping();
Schedule::command('orders:expire-unpaid')->everyTenMinutes()->withoutOverlapping();
