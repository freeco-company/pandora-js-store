<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Observers\ArticleComplianceObserver;
use App\Observers\OrderObserver;
use App\Observers\ProductComplianceObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Auto-sanitize text on every save (defense in depth)
        Product::observe(ProductComplianceObserver::class);
        Article::observe(ArticleComplianceObserver::class);

        // Auto-blacklist on COD no-pickup
        Order::observe(OrderObserver::class);
    }
}
