<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function stats()
    {
        $activeProductsCount = Product::where('status', 'active')->count();
        $activeCategoriesCount = Category::where('status', 'active')->count();
        $ordersCount = Order::count();
        $usersCount = User::count();
        $activeCouponsCount = Coupon::where('status', 'active')->count();
        $totalRevenue = Order::where('payment_status', 'paid')->sum('total_amount');

        // Last 6 months monthly revenue (including months with 0 revenue)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M');
            $yearMonth = $date->format('Y-m');

            $revenue = Order::where('payment_status', 'paid')
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total_amount');

            $monthlyRevenue[] = [
                'month' => $monthName,
                'revenue' => (float)$revenue,
            ];
        }

        // Order status distribution
        $statusCounts = Order::select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->get()
            ->pluck('count', 'order_status')
            ->toArray();

        // Enforce all statuses are represented
        $statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $orderStatusData = [];
        foreach ($statuses as $status) {
            $orderStatusData[] = [
                'status' => $status,
                'count' => $statusCounts[$status] ?? 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'activeProducts' => $activeProductsCount,
                'activeCategories' => $activeCategoriesCount,
                'totalOrders' => $ordersCount,
                'totalUsers' => $usersCount,
                'activeCoupons' => $activeCouponsCount,
                'totalRevenue' => (float)$totalRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'orderStatusDistribution' => $orderStatusData,
            ]
        ]);
    }
}
