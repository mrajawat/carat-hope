<?php

namespace App\Http\Controllers\Public;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerOrderController extends Controller
{
    public const SORT_OPTIONS = [
        'newest' => 'Newest First',
        'oldest' => 'Oldest First',
        'amount_high' => 'Amount: High to Low',
        'amount_low' => 'Amount: Low to High',
    ];

    /**
     * Dropdown / select box values for the customer orders screens
     * (order filters, checkout payment method, tracking status labels).
     */
    public function options()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'order_statuses' => $this->present(Order::ORDER_STATUSES),
                'payment_statuses' => $this->present(Order::PAYMENT_STATUSES),
                'payment_methods' => $this->present(Order::PAYMENT_METHODS),
                'shipment_statuses' => collect(ShipmentStatus::cases())
                    ->map(fn ($case) => [
                        'value' => $case->value,
                        'label' => Str::headline($case->value),
                    ])->values(),
                'sort_options' => $this->present(self::SORT_OPTIONS),
            ]
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:' . implode(',', array_keys(Order::ORDER_STATUSES)),
            'payment_status' => 'nullable|in:' . implode(',', array_keys(Order::PAYMENT_STATUSES)),
            'sort' => 'nullable|in:' . implode(',', array_keys(self::SORT_OPTIONS)),
        ]);

        $userId = $request->user()->id;

        $query = Order::with('order_items')
            ->where('user_id', $userId)
            ->when($request->filled('status'), fn ($q) => $q->where('order_status', $request->status))
            ->when($request->filled('payment_status'), fn ($q) => $q->where('payment_status', $request->payment_status));

        match ($request->input('sort', 'newest')) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'amount_high' => $query->orderBy('total_amount', 'desc'),
            'amount_low' => $query->orderBy('total_amount', 'asc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $orders = $query->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function show(Request $request, $id)
    {
        $userId = $request->user()->id;

        $order = Order::with('order_items')
            ->where('id', $id)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        if ($order->user_id !== $userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this order.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    protected function present(array $options)
    {
        return collect($options)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();
    }
}
