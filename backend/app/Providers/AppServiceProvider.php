<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Observers\ArticleComplianceObserver;
use App\Observers\BannerObserver;
use App\Observers\CampaignObserver;
use App\Observers\CustomerObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductComplianceObserver;
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

        // Bust product cache when campaigns change
        Campaign::observe(CampaignObserver::class);

        // Bust Next.js + Cloudflare cache when banners change
        Banner::observe(BannerObserver::class);

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
