<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    protected Api $api;

    public function __construct()
    {
        $this->api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Create a Razorpay order for a local order and store its id.
     * Amount is sent in the smallest currency unit (paise/cents).
     */
    public function createOrder(Order $order): array
    {
        $rzpOrder = $this->api->order->create([
            'receipt' => 'order_' . $order->id,
            'amount' => (int) round($order->total_amount * 100),
            'currency' => $order->currency,
            'notes' => ['order_id' => (string) $order->id],
        ]);

        $order->update(['razorpay_order_id' => $rzpOrder['id']]);

        return [
            'key' => config('services.razorpay.key'),
            'razorpay_order_id' => $rzpOrder['id'],
            'amount' => $rzpOrder['amount'],
            'currency' => $rzpOrder['currency'],
            'name' => config('app.name'),
            'prefill' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'contact' => $order->customer_phone,
            ],
        ];
    }

    public function verifyPaymentSignature(string $razorpayOrderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            $this->api->utility->verifyWebhookSignature($payload, $signature, config('services.razorpay.webhook_secret'));
            return true;
        } catch (SignatureVerificationError $e) {
            return false;
        }
    }

    /**
     * Mark the order paid. Idempotent: safe to call from both verify endpoint and webhook.
     */
    public function markPaid(string $razorpayOrderId, string $paymentId): ?Order
    {
        return DB::transaction(function () use ($razorpayOrderId, $paymentId) {
            $order = Order::where('razorpay_order_id', $razorpayOrderId)->lockForUpdate()->first();

            if (!$order) {
                Log::warning('Razorpay: order not found for payment', compact('razorpayOrderId', 'paymentId'));
                return null;
            }

            if ($order->payment_status === 'paid') {
                return $order;
            }

            if ($order->order_status === 'cancelled') {
                // Paid after the order expired — needs a manual refund/reinstate decision.
                Log::critical('Razorpay: payment received for cancelled order', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                ]);
            }

            $order->update([
                'payment_status' => 'paid',
                'razorpay_payment_id' => $paymentId,
                'paid_at' => now(),
                'order_status' => $order->order_status === 'pending' ? 'processing' : $order->order_status,
            ]);

            return $order;
        });
    }

    /**
     * Cancel an unpaid order and put its stock back.
     */
    public function cancelUnpaid(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::with('order_items')->lockForUpdate()->find($order->id);

            if (!$order || $order->payment_status !== 'unpaid' || $order->order_status !== 'pending') {
                return;
            }

            foreach ($order->order_items as $item) {
                Product::where('id', $item->product_id)->increment('stock_qty', $item->quantity);
            }

            $order->update(['order_status' => 'cancelled']);
        });
    }
}
