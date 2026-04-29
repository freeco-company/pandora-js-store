<?php

use App\Http\Controllers\Api\AiVisitController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BundleController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartEventController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\FranchiseConsultationController;
use App\Http\Controllers\Api\IdentityWebhookController;
use App\Http\Controllers\Api\Internal\ConversionMonthlyPurchasesController;
use App\Http\Controllers\Api\Internal\ConversionOrdersController;
use App\Http\Controllers\Api\LineWebhookController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\OrderConfirmationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PopupController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SocialProofController;
use App\Http\Controllers\Api\StockNotificationController;
use App\Http\Controllers\Api\VisitController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->middleware('throttle:strict');
Route::post('/orders/check-cod', [OrderController::class, 'checkCod'])->middleware('throttle:strict');

// COD pending-confirmation flow — frontend polls status; LINE webhook delivers postback.
Route::get('/orders/{orderNumber}/confirmation-status', [OrderConfirmationController::class, 'status']);
Route::post('/line/webhook', [LineWebhookController::class, 'handle'])
    ->withoutMiddleware(['web']);

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

// Real social proof — cumulative sales + recent viewers for product pages.
Route::get('/products/{slug}/social-proof', [SocialProofController::class, 'show']);

// AI traffic counter — called by Next.js proxy when AI bot UA or AI-origin
// referer is detected. Aggregates by (date, bot_type, source).
Route::post('/track/ai-visit', [AiVisitController::class, 'store'])
    ->middleware('throttle:600,1'); // 10/s burst cap — upsert is cheap, AI bots crawl fast

// Human visit logger — called client-side by Analytics.tsx on every route
// change. Inserts one raw row per hit for later UV/source/device breakdowns.
Route::post('/track/visit', [VisitController::class, 'store'])
    ->middleware('throttle:600,1');

// Cart event logger — fired from CartProvider alongside GTM dataLayer pushes
// so the pipeline report can compute funnel rates without GA4 API access.
// Throttle is generous because one user can easily trigger 5+ events/min
// (view_item on every product card hover, rapid add_to_cart etc.).
Route::post('/track/cart-event', [CartEventController::class, 'store'])
    ->middleware('throttle:1000,1');

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

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::post('/wishlist/sync', [WishlistController::class, 'sync']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);
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
Route::get('/reviews', [ReviewController::class, 'aggregate']);
Route::get('/products/{slug}/reviews', [ReviewController::class, 'index']);

// Back-in-stock notify
Route::post('/products/{slug}/notify-stock', [StockNotificationController::class, 'subscribe'])->middleware('throttle:strict');

// Campaigns (活動) with nested bundles
Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/{slug}', [CampaignController::class, 'show']);

// Bundle detail — /api/bundles/{slug}. 404 when parent campaign not running.
Route::get('/bundles/{slug}', [BundleController::class, 'show']);

// Pandora Core identity webhook — platform-side single source of truth pushes
// here when group_users / group_user_identities change. HMAC + replay 防護
// 由 'identity.webhook' middleware 處理。POST /api/internal/identity/webhook
//
// withoutMiddleware('throttle:api')：內部 server-to-server 流量不該走 60/min
// public rate limit（backfill 時 platform 一次推 100+ events 會被 429 踢成
// dead_letter）。HMAC + nonce dedup 已足夠擋 abuse。
Route::post('/internal/identity/webhook', IdentityWebhookController::class)
    ->middleware('identity.webhook')
    ->withoutMiddleware([ThrottleRequests::class.':api']);

// Pandora Conversion (py-service, ADR-003 §3.2) — outbound query for the
// loyalist rule's "≥2 母艦復購 in last 90d" branch. HMAC-signed; see
// VerifyConversionInternalSignature for header / signing-base spec.
//
// withoutMiddleware('throttle:api')：server-to-server，公開 60/min 限流會在
// 批次評估 lifecycle 時誤殺。HMAC + timestamp window 已足夠擋 abuse。
Route::get(
    '/internal/conversion/customer-orders/{pandora_user_uuid}',
    ConversionOrdersController::class
)
    ->middleware('conversion.internal')
    ->withoutMiddleware([ThrottleRequests::class.':api']);

// ADR-008 §2.2 段 1 訊號 — franchise consultation form. STUB until business
// team finalises the form schema; see controller PHPDoc for TODO list.
Route::post('/franchise/consultation', [FranchiseConsultationController::class, 'store'])
    ->middleware('throttle:strict');

// Pandora Conversion (py-service, ADR-008 §2.3) — feeds the段 2 訊號 (a)
// "月進貨連續 N 個月 > NT$30K". Same HMAC + no public throttle as above.
Route::get(
    '/internal/conversion/customer-monthly-purchases/{pandora_user_uuid}',
    ConversionMonthlyPurchasesController::class
)
    ->middleware('conversion.internal')
    ->withoutMiddleware([ThrottleRequests::class.':api']);

// ADR-009 Phase B inbound — py-service gamification outbox dispatcher pushes
// level_up / achievement_awarded / outfit_unlocked here. HMAC + nonce dedup
// handled by middleware. No public throttle (server-to-server, batched).
Route::post(
    '/internal/gamification/webhook',
    [\App\Http\Controllers\Api\GamificationWebhookController::class, 'handle']
)
    ->middleware('gamification.webhook')
    ->withoutMiddleware([ThrottleRequests::class.':api']);
