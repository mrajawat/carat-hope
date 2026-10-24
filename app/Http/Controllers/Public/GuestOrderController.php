<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Order view for buyers who checked out without logging in. The order access
 * token returned by checkout (data.order_access_token) unlocks that one order,
 * sent as the X-Order-Token header or ?token= (for links in emails). To see all
 * their orders, a guest logs in with an OTP via /guest/send-otp instead.
 */
class GuestOrderController extends Controller
{
    public function show(Request $request, $id)
    {
        $token = $request->header('X-Order-Token') ?: $request->query('token');

        $order = is_string($token) && $token !== ''
            ? Order::with([
                'order_items',
                'shipment.trackingEvents' => fn ($q) => $q->orderBy('event_time', 'desc'),
                'shipment.shippingMethod',
            ])->where('id', $id)->whereNotNull('access_token_hash')->first()
            : null;

        // Same response for a missing order and a wrong token, so order ids cannot be probed.
        if (!$order || !hash_equals($order->access_token_hash, hash('sha256', $token))) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
}
