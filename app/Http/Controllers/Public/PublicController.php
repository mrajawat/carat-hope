<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\RegionDetectionService;
use App\Http\Resources\ProductListResource;
use App\Http\Resources\ProductDetailResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function banners()
    {
        $banners = cache()->remember('public_banners', now()->addHours(24), function () {
            return Banner::where('status', 'active')->orderBy('created_at', 'desc')->get();
        });

        return response()->json([
            'success' => true,
            'data' => $banners
        ]);
    }

    public function categories()
    {
        $categories = cache()->remember('public_categories', now()->addHours(24), function () {
            return Category::where('status', 'active')->orderBy('name', 'asc')->get();
        });

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function products(Request $request)
    {
        try {
            $region = app(RegionDetectionService::class)->detect($request);
            ProductListResource::$regionId = $region->id;

            $query = Product::with([
                'category',
                'product_images',
                'variants' => fn ($q) => $q->where('is_active', true),
                'variants.prices' => fn ($q) => $q->where('region_id', $region->id),
                'prices' => fn ($q) => $q->where('region_id', $region->id),
            ])
                ->withCount(['reviews' => function($q) {
                    $q->where('status', 'approved');
                }])
                ->withAvg(['reviews as avg_rating' => function($q) {
                    $q->where('status', 'approved');
                }], 'rating')
                ->where('status', 'active');

            // Filter by category
            if ($request->has('category_id') && !empty($request->category_id)) {
                $query->where('category_id', $request->category_id);
            }

            // Search by name or SKU
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            // Featured products
            if ($request->has('featured') && $request->featured == 'true') {
                $query->where('is_featured', true);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'newest');
            if ($sortBy === 'price_asc') {
                $query->orderBy('price', 'asc');
            } elseif ($sortBy === 'price_desc') {
                $query->orderBy('price', 'desc');
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $products = $query->paginate($request->get('per_page', 12));

            return ProductListResource::collection($products)->additional([
                'success' => true,
                'status' => true,
                'message' => 'Products retrieved successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Product listing failed: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->map(fn ($t) => ($t['file'] ?? '') . ':' . ($t['line'] ?? ''))->toArray(),
            ], 500);
        }
    }

    public function productDetail($slug_or_id, Request $request)
    {
        $region = app(RegionDetectionService::class)->detect($request);
        ProductDetailResource::$regionId = $region->id;

        $product = Product::with([
            'category',
            'product_images',
            'variants' => fn ($q) => $q->where('is_active', true),
            'variants.attributeValues',
            'variants.prices' => fn ($q) => $q->where('region_id', $region->id),
            'prices' => fn ($q) => $q->where('region_id', $region->id),
            'processingProfile',
            'shippingProfile',
            'customOptions',
        ])
            ->where('status', 'active')
            ->where(function ($query) use ($slug_or_id) {
                $query->where('id', $slug_or_id)
                      ->orWhere('sku', $slug_or_id);
            })
            ->firstOrFail();

        return (new ProductDetailResource($product))->additional([
            'success' => true,
            'status' => true,
            'message' => 'Product retrieved successfully',
        ]);
    }

    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper($request->code);
        $coupon = Coupon::where('code', $code)
            ->where('status', 'active')
            ->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon code.'
            ], 400);
        }

        if (Carbon::parse($coupon->expiry_date)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon code has expired.'
            ], 400);
        }

        if ($coupon->usage_count >= $coupon->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon usage limit has been reached.'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'discount_type' => $coupon->discount_type,
                'discount_value' => (float)$coupon->discount_value,
            ]
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string',
            'billing_address' => 'required|array',
            'shipping_address' => 'nullable|array',
            'notes' => 'nullable|string',
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'coupon_code' => 'nullable|string',
        ]);

        $existingUser = null;
        if (!auth('sanctum')->check()) {
            $userByPhone = User::where('phone', $request->customer_phone)->first();
            $userByEmail = User::where('email', $request->customer_email)->first();

            if ($userByPhone && $userByEmail && $userByPhone->id !== $userByEmail->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'The provided email and phone number are associated with different accounts. Please log in or use matching details.'
                ], 400);
            }
            $existingUser = $userByPhone ?? $userByEmail;
        }

        return DB::transaction(function () use ($request, $existingUser) {
            if (auth('sanctum')->check()) {
                $user = auth('sanctum')->user();
            } else {
                $user = $existingUser;
                if (!$user) {
                    $user = User::create([
                        'name' => $request->customer_name,
                        'email' => $request->customer_email,
                        'phone' => $request->customer_phone,
                        'password' => null, // Guest checkout
                        'is_guest' => true,
                        'status' => 'active',
                    ]);
                }
            }

            $totalAmount = 0.00;
            $itemsToProcess = [];

            // Calculate initial amount and check stock
            $countryCode = $this->getDetectedCountry();
            foreach ($request->items as $item) {
                $product = Product::with('product_images')->lockForUpdate()->findOrFail($item['product_id']);

                if ($product->status !== 'active') {
                    throw new \Exception("Product {$product->name} is not available for purchase.");
                }

                if ($product->stock_qty < $item['quantity']) {
                    throw new \Exception("Insufficient stock for product {$product->name}. Only {$product->stock_qty} left.");
                }

                // Decrement stock
                $product->stock_qty -= $item['quantity'];
                $product->save();

                $price = $product->getLocalPrice($countryCode);
                $subtotal = $price * $item['quantity'];
                $totalAmount += $subtotal;

                // Find primary image or fallback
                $primaryImg = $product->product_images->firstWhere('is_primary', true) 
                    ?? $product->product_images->first() 
                    ?? (object)['image_path' => ''];

                $itemsToProcess[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_image' => $primaryImg->image_path,
                    'quantity' => $item['quantity'],
                    'price' => (float)$price,
                ];
            }

            // Apply coupon if provided
            $coupon = null;
            if ($request->has('coupon_code') && !empty($request->coupon_code)) {
                $code = strtoupper($request->coupon_code);
                $coupon = Coupon::where('code', $code)
                    ->where('status', 'active')
                    ->first();

                if (!$coupon) {
                    throw new \Exception('Invalid coupon code.');
                }

                if (Carbon::parse($coupon->expiry_date)->isPast()) {
                    throw new \Exception('Coupon code has expired.');
                }

                if ($coupon->usage_count >= $coupon->usage_limit) {
                    throw new \Exception('Coupon usage limit reached.');
                }

                // Apply discount
                if ($coupon->discount_type === 'percentage') {
                    $discount = ($totalAmount * $coupon->discount_value) / 100;
                } else {
                    $discount = $coupon->discount_value;
                }

                $totalAmount = max(0.00, $totalAmount - $discount);
                
                // Add 8% tax matching the frontend
                $tax = $totalAmount * 0.08;
                $totalAmount = $totalAmount + $tax;

                // Increment usage
                $coupon->usage_count += 1;
                $coupon->save();
            }

            // Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'total_amount' => $totalAmount,
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'billing_address' => $request->billing_address,
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes,
                'payment_method' => $request->payment_method,
            ]);

            // Create Order Items snapshots
            foreach ($itemsToProcess as $processedItem) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $processedItem['product_id'],
                    'product_name' => $processedItem['product_name'],
                    'product_image' => $processedItem['product_image'],
                    'quantity' => $processedItem['quantity'],
                    'price' => $processedItem['price'],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'total_amount' => (float)$totalAmount,
                    'message' => 'Order placed successfully.'
                ]
            ], 201);
        });
    }

    protected function getDetectedCountry()
    {
        $request = request();
        
        // 1. Check for common CDN geo headers first (extremely fast, 0ms)
        $cdnCountry = $request->header('CF-IPCountry') 
            ?? $request->header('X-Vercel-IP-Country') 
            ?? $request->header('CloudFront-Viewer-Country') 
            ?? $request->header('X-AppEngine-Country');
            
        if ($cdnCountry && strlen($cdnCountry) === 2) {
            return strtoupper($cdnCountry);
        }
        
        // Vercel, Cloudflare, ya kisi aur Proxy ke peeche hone par real IP nikalna
        $ip = $request->header('CF-Connecting-IP') 
            ?? $request->header('X-Vercel-Forwarded-For') 
            ?? $request->header('X-Forwarded-For') 
            ?? $request->ip();
            
        // Agar multiple IPs comma-separated hain, toh pehla IP lo
        if (strpos($ip, ',') !== false) {
            $ip = explode(',', $ip)[0];
        }
        $ip = trim($ip);
        
        // For local testing
        if ($ip === '127.0.0.1' || $ip === '::1') {
            $ip = '103.116.12.1'; // Example Indian IP
        }
        
        // 2. Cache the result by IP to avoid making external HTTP requests on every single page load
        $cacheKey = 'geoip_country_' . md5($ip);
        
        return cache()->remember($cacheKey, now()->addDays(7), function () use ($ip) {
            try {
                $location = \Stevebauman\Location\Facades\Location::get($ip);
                return $location ? strtoupper($location->countryCode) : 'US';
            } catch (\Exception $e) {
                // Fail silently and fallback to US if API is down/throttled or has network issues
                return 'US';
            }
        });
    }
}
