<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\StockNotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PopupController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

// Auth (Google + LINE OAuth)
Route::middleware('throttle:auth')->group(function () {
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
    Route::get('/auth/line', [AuthController::class, 'redirectToLine']);
    Route::get('/auth/line/callback', [AuthController::class, 'handleLineCallback']);
});
Route::middleware('auth:sanctum')->get('/auth/me', [AuthController::class, 'me']);

// Products
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slug}', [ProductController::class, 'show']);
Route::get('/product-categories', [ProductController::class, 'categories']);

// Cart
Route::post('/cart/calculate', [CartController::class, 'calculate']);

// Coupons
Route::post('/coupons/validate', [CouponController::class, 'validate'])->middleware('throttle:strict');

// Orders
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:strict');
Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
Route::post('/orders/check-cod', [OrderController::class, 'checkCod'])->middleware('throttle:strict');

// Payment
Route::post('/payment/create', [PaymentController::class, 'createPayment'])->middleware('throttle:strict');
Route::post('/payment/ecpay/callback', [PaymentController::class, 'ecpayCallback']);

// Articles
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/article-categories', [ArticleController::class, 'categories']);

// Banners & Popups
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/popups', [PopupController::class, 'index']);

// Customer gamification dashboard (requires auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/customer/dashboard', [CustomerController::class, 'dashboard']);
    Route::post('/customer/mascot/outfit', [CustomerController::class, 'setOutfit']);
    Route::post('/customer/mascot/backdrop', [CustomerController::class, 'setBackdrop']);
    Route::post('/customer/activation', [CustomerController::class, 'markActivation']);
    Route::get('/customer/orders', [OrderController::class, 'customerOrders']);

    // Reviews (authenticated)
    Route::get('/customer/reviewable', [ReviewController::class, 'reviewable']);
    Route::post('/customer/reviews', [ReviewController::class, 'store'])->middleware('throttle:strict');

    // Profile + address book
    Route::get('/customer/profile', [CustomerProfileController::class, 'show']);
    Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
    Route::get('/customer/addresses', [CustomerProfileController::class, 'addressIndex']);
    Route::post('/customer/addresses', [CustomerProfileController::class, 'addressStore']);
    Route::put('/customer/addresses/{address}', [CustomerProfileController::class, 'addressUpdate']);
    Route::delete('/customer/addresses/{address}', [CustomerProfileController::class, 'addressDestroy']);
});

// ECPay 物流 CVS 超商地圖選店（需開放 POST 給 ECPay callback）
Route::get('/logistics/cvs/init', [LogisticsController::class, 'init']);
Route::post('/logistics/cvs/callback', [LogisticsController::class, 'callback'])->withoutMiddleware(['web']);
Route::get('/logistics/cvs/pick/{token}', [LogisticsController::class, 'pick']);

// ECPay Express/Create 建立物流單 — 回傳 AllPayLogisticsID 等資訊
// 綠界這兩條 callback 都是 server-to-server POST，沒有 CSRF
Route::post('/logistics/ecpay/reply', [LogisticsController::class, 'ecpayReply'])->withoutMiddleware(['web']);
Route::post('/logistics/ecpay/status', [LogisticsController::class, 'ecpayStatus'])->withoutMiddleware(['web']);

// Reviews (public)
Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);

// Back-in-stock notify
Route::post('/products/{slug}/notify-stock', [StockNotificationController::class, 'subscribe'])->middleware('throttle:strict');

// Campaigns (活動管理)
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/{slug}', [CampaignController::class, 'show']);
