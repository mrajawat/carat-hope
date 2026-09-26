<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    public function __construct(protected RazorpayService $razorpay)
    {
    }

    /**
     * Called by the frontend from Razorpay Checkout's success handler.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string|max:64',
            'razorpay_payment_id' => 'required|string|max:64',
            'razorpay_signature' => 'required|string|max:255',
        ]);

        if (!$this->razorpay->verifyPaymentSignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed.',
            ], 400);
        }

        try {
            $order = $this->razorpay->markPaid($validated['razorpay_order_id'], $validated['razorpay_payment_id']);
        } catch (\Throwable $e) {
            Log::error('Razorpay verify failed', ['error' => $e->getMessage(), 'payload' => $validated]);
            return response()->json([
                'success' => false,
                'message' => 'Could not update order. Your payment is safe; please contact support.',
            ], 500);
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'order_id' => $order->id,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
            ],
        ]);
    }

    /**
     * Razorpay server-to-server webhook. Covers cases where the user closes
     * the browser before the frontend calls verify().
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');

        if ($signature === '' || !$this->razorpay->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Razorpay webhook: invalid signature');
            return response()->json(['success' => false], 400);
        }

        $event = $request->input('event');
        $payment = $request->input('payload.payment.entity', []);

        try {
            if (in_array($event, ['payment.captured', 'order.paid'], true) && !empty($payment['order_id'])) {
                $this->razorpay->markPaid($payment['order_id'], $payment['id']);
            } elseif ($event === 'payment.failed') {
                // Customer can retry on the same Razorpay order, so the order stays pending.
                Log::info('Razorpay payment failed', [
                    'razorpay_order_id' => $payment['order_id'] ?? null,
                    'reason' => $payment['error_description'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Razorpay webhook processing failed', ['event' => $event, 'error' => $e->getMessage()]);
            // Non-2xx makes Razorpay retry.
            return response()->json(['success' => false], 500);
        }

        return response()->json(['success' => true]);
    }
}
