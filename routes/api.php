<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\Public\PublicController;
use App\Http\Controllers\Public\CustomerAuthController;
use App\Http\Controllers\Public\CustomerOrderController;
use App\Http\Controllers\Public\RazorpayController;
use App\Http\Controllers\Public\ReviewController;
use App\Http\Controllers\Public\GuestAuthController;
use App\Http\Controllers\Public\GuestOrderController;
use App\Http\Controllers\Public\DeviceTokenController;
use App\Http\Controllers\Admin\AttributeController;
use App\Http\Controllers\Admin\CategoryAttributeController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\ProductOptionController;
use App\Http\Controllers\Admin\ProductCustomOptionController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Frontend\ProductPriceController;
use App\Http\Controllers\Public\ShippingController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\Admin\ShippingZoneController;
use App\Http\Controllers\Admin\ShippingMethodController;
use App\Http\Controllers\Admin\DeliveryEstimateController;
use App\Http\Controllers\Admin\ShippingThresholdController;
use App\Http\Controllers\Admin\ProcessingProfileController;
use App\Http\Controllers\Admin\ShippingProfileController;
use App\Http\Controllers\Public\CustomOrderController;
use App\Http\Controllers\Public\CartController;
use App\Http\Controllers\Public\WishlistController;
use App\Http\Controllers\Admin\CustomOrderController as AdminCustomOrderController;
use App\Http\Controllers\Public\AppointmentController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Client/Frontend Routes
Route::prefix('public')->middleware('throttle:public')->group(function () {
    Route::get('/banners', [PublicController::class, 'banners']);
    Route::get('/categories', [PublicController::class, 'categories']);
    Route::get('/categories/tree', [PublicController::class, 'categoryTree']);
    Route::get('/products', [PublicController::class, 'products']);
    Route::get('/products/{slug_or_id}', [PublicController::class, 'productDetail']);
    Route::get('/products/{productId}/reviews', [ReviewController::class, 'index']);

    // Custom Order (Personalised Jewellery) Dropdowns: categories, metal types + variants, gemstones
    Route::get('/custom-orders/options', [CustomOrderController::class, 'options']);
    Route::get('/custom-orders/metal-types/{id}/variants', [CustomOrderController::class, 'metalVariants'])->whereNumber('id');

    // Book an Appointment: active service types (step 1 of the form)
    Route::get('/appointments/service-types', [AppointmentController::class, 'serviceTypes']);

    // Customer Order Dropdowns (statuses, payment methods, sort options)
    Route::get('/orders/options', [CustomerOrderController::class, 'options']);

    // Cart: works for logged-in customers (Bearer token) and guests (X-Guest-Token header).
    // A guest's first add returns data.guest_token to send on later calls.
    Route::get('/cart', [CartController::class, 'show']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::patch('/cart/items/{id}', [CartController::class, 'updateItem'])->whereNumber('id');
    Route::delete('/cart/items/{id}', [CartController::class, 'removeItem'])->whereNumber('id');
    Route::delete('/cart', [CartController::class, 'clear']);

    // Guest order confirmation/tracking with the order_access_token from checkout
    Route::get('/guest/orders/{id}', [GuestOrderController::class, 'show'])->whereNumber('id');

    // Sensitive Public Routes (Protected with stricter rate limiting)
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/coupons/validate', [PublicController::class, 'validateCoupon']);
        Route::post('/orders/checkout', [PublicController::class, 'checkout']);
        Route::post('/payments/razorpay/verify', [RazorpayController::class, 'verify']);
        Route::post('/custom-orders', [CustomOrderController::class, 'store']);
        Route::post('/appointments', [AppointmentController::class, 'store']);

        // Customer Authentication (Guest)
        Route::post('/register', [CustomerAuthController::class, 'register']);
        Route::post('/register/verify-email', [CustomerAuthController::class, 'verifyRegisterEmail']);
        Route::post('/register/resend-otp', [CustomerAuthController::class, 'resendRegisterOtp']);
        Route::post('/login/send-otp', [CustomerAuthController::class, 'login']);
        Route::post('/login/verify-otp', [CustomerAuthController::class, 'verifyOtp']);

        // Guest Auth
        Route::post('/guest/send-otp', [GuestAuthController::class, 'sendOtp']);
        Route::post('/guest/verify-otp', [GuestAuthController::class, 'verifyOtp']);
    });

    // Authenticated Customer Profile & Orders
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [CustomerAuthController::class, 'logout']);
        Route::get('/profile', [CustomerAuthController::class, 'profile']);
        Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);

        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::get('/orders/{id}', [CustomerOrderController::class, 'show'])->whereNumber('id');
        Route::get('/orders/{order}/shipment', [ShippingController::class, 'getOrderShipment']);

        Route::get('/custom-orders', [CustomOrderController::class, 'index']);
        Route::get('/custom-orders/{id}', [CustomOrderController::class, 'show'])->whereNumber('id');

        Route::post('/cart/merge', [CartController::class, 'merge']);

        // Wishlist (guests keep one on the device and push it to /wishlist/sync after login)
        Route::get('/wishlist', [WishlistController::class, 'index']);
        Route::get('/wishlist/product-ids', [WishlistController::class, 'productIds']);
        Route::post('/wishlist/items', [WishlistController::class, 'store']);
        Route::post('/wishlist/sync', [WishlistController::class, 'sync']);
        Route::delete('/wishlist/items/{id}', [WishlistController::class, 'destroy'])->whereNumber('id');
        Route::delete('/wishlist/products/{productId}', [WishlistController::class, 'destroyProduct'])->whereNumber('productId');
        Route::delete('/wishlist', [WishlistController::class, 'clear']);

        // Guest -> registered customer (after logging in with a guest OTP)
        Route::post('/guest/claim-account', [GuestAuthController::class, 'claimAccount']);

        Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);

        // Device Token Routes
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    });

    Route::post('/shipping/estimate', [ShippingController::class, 'getEstimate']);
});

// Razorpay webhook (server-to-server, signature verified, no throttle)
Route::post('/webhooks/razorpay', [RazorpayController::class, 'webhook']);

// Admin Authentication (Public Route - rate limited)
Route::post('/admin/login', [AdminAuthController::class, 'login'])->middleware('throttle:sensitive');

// Admin Protected Routes (Sanctum Protected)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    // Dashboard Stats
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);

    // Banners CRUD + Toggle Status
    Route::apiResource('/banners', BannerController::class);
    Route::patch('/banners/{id}/toggle-status', [BannerController::class, 'toggleStatus']);

    // Categories CRUD + Toggle Status
    Route::apiResource('/categories', CategoryController::class);
    Route::patch('/categories/{id}/toggle-status', [CategoryController::class, 'toggleStatus']);

    // Subcategories API
    Route::get('/subcategories', [SubcategoryController::class, 'index']);
    Route::post('/subcategories', [SubcategoryController::class, 'store']);
    Route::get('/subcategories/{id}', [SubcategoryController::class, 'show']);
    Route::put('/subcategories/{id}', [SubcategoryController::class, 'update']);
    Route::delete('/subcategories/{id}', [SubcategoryController::class, 'destroy']);
    Route::patch('/subcategories/{id}/toggle-status', [SubcategoryController::class, 'toggleStatus']);

    // Nested Subcategories API under category
    Route::get('/categories/{category}/subcategories', [SubcategoryController::class, 'subcategoriesByCategory']);
    Route::post('/categories/{category}/subcategories', [SubcategoryController::class, 'storeByCategory']);

    // Products CRUD + Toggle Status + Toggle Featured + Preview
    Route::post('/products/preview', [ProductController::class, 'preview']);
    Route::post('/products/bulk-toggle-featured', [ProductController::class, 'bulkToggleFeatured']);
    Route::apiResource('/products', ProductController::class);
    Route::get('/products/{product}/custom-options', [ProductCustomOptionController::class, 'index']);
    Route::post('/products/{product}/custom-options', [ProductCustomOptionController::class, 'store']);
    Route::put('/custom-options/{customOption}', [ProductCustomOptionController::class, 'update']);
    Route::delete('/custom-options/{customOption}', [ProductCustomOptionController::class, 'destroy']);
    Route::patch('/products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
    Route::patch('/products/{id}/toggle-featured', [ProductController::class, 'toggleFeatured']);

    // Coupons CRUD + Toggle Status
    Route::apiResource('/coupons', CouponController::class);
    Route::patch('/coupons/{id}/toggle-status', [CouponController::class, 'toggleStatus']);

    // Users (Customers) Management
    Route::get('/users', [UserController::class, 'index']);
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Orders Management
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);

    // Custom Orders (Personalised Jewellery) Management
    Route::get('/custom-orders', [AdminCustomOrderController::class, 'index']);
    Route::get('/custom-orders/{id}', [AdminCustomOrderController::class, 'show'])->whereNumber('id');
    Route::patch('/custom-orders/{id}/status', [AdminCustomOrderController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/custom-orders/{id}', [AdminCustomOrderController::class, 'destroy'])->whereNumber('id');

    // Appointments Management
    Route::get('/appointments', [AdminAppointmentController::class, 'index']);
    Route::get('/appointments/{id}', [AdminAppointmentController::class, 'show'])->whereNumber('id');
    Route::patch('/appointments/{id}/status', [AdminAppointmentController::class, 'updateStatus'])->whereNumber('id');

    // Reviews Management
    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::patch('/reviews/{id}/status', [AdminReviewController::class, 'updateStatus']);
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

    // Admin Product Variations & Global Region Pricing Routes
    Route::get('/attributes/{attribute}/values', [AttributeController::class, 'values']);
    Route::post('/attributes/{attribute}/values', [AttributeController::class, 'storeValue']);
    // Metal type -> variants links (e.g. Gold -> 14k, 18k) for the custom order form
    Route::get('/attributes/values/{value}/variants', [AttributeController::class, 'valueVariants']);
    Route::put('/attributes/values/{value}/variants', [AttributeController::class, 'syncValueVariants']);
    Route::delete('/attributes/values/{value}', [AttributeController::class, 'destroyValue']);
    Route::apiResource('/attributes', AttributeController::class);

    Route::get('/category-attributes', [CategoryAttributeController::class, 'index']);
    Route::post('/category-attributes', [CategoryAttributeController::class, 'store']);
    Route::delete('/category-attributes/{id}', [CategoryAttributeController::class, 'destroy']);

    Route::get('/product-options', [ProductOptionController::class, 'index']);

    Route::post('/products/{product}/variants/generate-combinations', [ProductVariantController::class, 'generateCombinations']);
    Route::get('/products/{product}/variants', [ProductVariantController::class, 'index']);
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::put('/variants/bulk-update', [ProductVariantController::class, 'bulkUpdate']);
    Route::put('/variants/{variant}', [ProductVariantController::class, 'update']);
    Route::delete('/variants/{variant}', [ProductVariantController::class, 'destroy']);
    Route::put('/variants/{variant}/prices', [ProductVariantController::class, 'bulkUpdatePricing']);

    Route::apiResource('/regions', RegionController::class);

    // Admin Shipping & Delivery Management
    Route::get('/shipments', [ShipmentController::class, 'index']);
    Route::get('/shipments/{shipment}', [ShipmentController::class, 'show']);
    Route::post('/orders/{order}/shipment', [ShipmentController::class, 'store']);
    Route::post('/shipments/{shipment}/status', [ShipmentController::class, 'updateStatus']);
    Route::post('/shipments/{shipment}/tracking-number', [ShipmentController::class, 'attachTrackingNumber']);
    Route::apiResource('/shipping-zones', ShippingZoneController::class);
    Route::apiResource('/shipping-methods', ShippingMethodController::class);
    Route::apiResource('/delivery-estimates', DeliveryEstimateController::class);
    Route::apiResource('/shipping-thresholds', ShippingThresholdController::class);
    Route::apiResource('/processing-profiles', ProcessingProfileController::class);
    Route::apiResource('/shipping-profiles', ShippingProfileController::class);
});

// Public Routes
Route::get('/categories/{category}/attributes', [CategoryAttributeController::class, 'getAttributesForCategory']);
Route::get('/regions', [RegionController::class, 'index']);
Route::get('/products/{product}/price', [ProductPriceController::class, 'getPrice']);
