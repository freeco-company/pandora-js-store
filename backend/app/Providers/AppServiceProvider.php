<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Observers\ArticleComplianceObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductComplianceObserver;
use Illuminate\Support\Facades\Event;
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
        // Register LINE Socialite driver
        Event::listen(SocialiteWasCalled::class, LineExtendSocialite::class);

        // Auto-sanitize text on every save (defense in depth)
        Product::observe(ProductComplianceObserver::class);
        Article::observe(ArticleComplianceObserver::class);

        // Auto-blacklist on COD no-pickup
        Order::observe(OrderObserver::class);
    }
}
