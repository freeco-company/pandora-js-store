<?php

namespace App\Providers;

use App\Events\OrderPaid;
use App\Listeners\PushOrderPaidToConversion;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Banner;
use App\Models\Bundle;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Popup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Review;
use App\Observers\ArticleCategoryObserver;
use App\Observers\ArticleComplianceObserver;
use App\Observers\BannerObserver;
use App\Observers\BundleObserver;
use App\Observers\CampaignObserver;
use App\Observers\CustomerObserver;
use App\Observers\OrderConversionObserver;
use App\Observers\OrderObserver;
use App\Observers\PopupObserver;
use App\Observers\ProductCategoryObserver;
use App\Observers\ProductComplianceObserver;
use App\Observers\ReviewObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Line\LineExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        // Register LINE Socialite driver
        Event::listen(SocialiteWasCalled::class, LineExtendSocialite::class);

        // Auto-sanitize text on every save (defense in depth)
        Product::observe(ProductComplianceObserver::class);
        Article::observe(ArticleComplianceObserver::class);

        // Auto-blacklist on COD no-pickup
        Order::observe(OrderObserver::class);

        // ADR-008 §2.3 — fires OrderPaid event on payment_status → paid
        // (separate from OrderObserver to keep concerns isolated).
        Order::observe(OrderConversionObserver::class);

        // ADR-008 §2.3 — push conversion event to py-service when an order
        // becomes paid. Queued; noop when env not configured.
        Event::listen(OrderPaid::class, PushOrderPaidToConversion::class);

        // Bust product cache when campaigns / bundles change
        Campaign::observe(CampaignObserver::class);
        Bundle::observe(BundleObserver::class);

        // Bust Next.js + Cloudflare cache when admin-editable surfaces change
        Banner::observe(BannerObserver::class);
        Popup::observe(PopupObserver::class);
        ProductCategory::observe(ProductCategoryObserver::class);
        ArticleCategory::observe(ArticleCategoryObserver::class);
        Review::observe(ReviewObserver::class);

        // Mirror customers.{email,phone,google_id,line_id} into customer_identities
        // for unified identity lookup + dedupe support.
        Customer::observe(CustomerObserver::class);
    }

    private function configureRateLimiting(): void
    {
        // Global API: 120 requests/minute per IP
        RateLimiter::for('api', fn (Request $request) =>
            Limit::perMinute(120)->by($request->ip())
        );

        // Strict: order creation, payment, coupon validation
        RateLimiter::for('strict', fn (Request $request) =>
            Limit::perMinute(10)->by($request->ip())
        );

        // Auth: OAuth redirects
        RateLimiter::for('auth', fn (Request $request) =>
            Limit::perMinute(20)->by($request->ip())
        );
    }
}
