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
use App\Http\Controllers\Public\ReviewController;
use App\Http\Controllers\Public\GuestAuthController;
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Client/Frontend Routes
Route::prefix('public')->middleware('throttle:public')->group(function () {
    Route::get('/banners', [PublicController::class, 'banners']);
    Route::get('/categories', [PublicController::class, 'categories']);
    Route::get('/products', [PublicController::class, 'products']);
    Route::get('/products/{slug_or_id}', [PublicController::class, 'productDetail']);
    Route::get('/products/{productId}/reviews', [ReviewController::class, 'index']);

    // Sensitive Public Routes (Protected with stricter rate limiting)
    Route::middleware('throttle:sensitive')->group(function () {
        Route::post('/coupons/validate', [PublicController::class, 'validateCoupon']);
        Route::post('/orders/checkout', [PublicController::class, 'checkout']);

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
        Route::get('/orders/{id}', [CustomerOrderController::class, 'show']);
        Route::get('/orders/{order}/shipment', [ShippingController::class, 'getOrderShipment']);

        Route::post('/products/{productId}/reviews', [ReviewController::class, 'store']);

        // Device Token Routes
        Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);
    });

    Route::post('/shipping/estimate', [ShippingController::class, 'getEstimate']);
});

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

    // Reviews Management
    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::patch('/reviews/{id}/status', [AdminReviewController::class, 'updateStatus']);
    Route::delete('/reviews/{id}', [AdminReviewController::class, 'destroy']);

    // Admin Product Variations & Global Region Pricing Routes
    Route::get('/attributes/{attribute}/values', [AttributeController::class, 'values']);
    Route::post('/attributes/{attribute}/values', [AttributeController::class, 'storeValue']);
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
