<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Bundle;
use App\Models\Campaign;
use App\Models\Popup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Review;
use App\Services\FrontendCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Asserts every admin-editable model triggers FrontendCacheService::purge()
 * on save, so Cloudflare + Next.js ISR don't keep serving stale content
 * after a Filament edit. Banner already had this — these tests catch the
 * regression for the rest.
 */
class FrontendCachePurgeTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, array{tags: array, paths: array}> */
    private array $purgeCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeCalls = [];
        $spy = Mockery::mock(FrontendCacheService::class);
        $spy->shouldReceive('purge')
            ->andReturnUsing(function (array $tags = [], array $paths = []) {
                $this->purgeCalls[] = ['tags' => $tags, 'paths' => $paths];
            });
        $this->app->instance(FrontendCacheService::class, $spy);
    }

    private function assertPurged(array $expectedTags, array $expectedPaths, string $message = ''): void
    {
        foreach ($this->purgeCalls as $call) {
            $tagsOk = empty(array_diff($expectedTags, $call['tags']));
            $pathsOk = empty(array_diff($expectedPaths, $call['paths']));
            if ($tagsOk && $pathsOk) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail($message ?: sprintf(
            "No purge call matched. Expected tags=%s paths=%s. Got %d calls: %s",
            json_encode($expectedTags),
            json_encode($expectedPaths),
            count($this->purgeCalls),
            json_encode($this->purgeCalls)
        ));
    }

    public function test_product_save_purges_frontend(): void
    {
        Product::create([
            'name' => '商品 A', 'slug' => 'p1', 'price' => 1000, 'is_active' => true,
        ]);

        $this->assertPurged(
            ['products', 'product:p1'],
            ['/products', '/products/p1'],
        );
    }

    public function test_article_save_purges_frontend(): void
    {
        Article::create([
            'title' => '文章', 'slug' => 'a1', 'content' => '<p>內容</p>',
            'status' => 'draft', 'source_type' => 'blog',
        ]);

        $this->assertPurged(['articles', 'article:a1'], ['/articles', '/articles/a1']);
    }

    public function test_campaign_save_purges_frontend(): void
    {
        Campaign::create([
            'name' => '活動', 'slug' => 'c1',
            'start_at' => now(), 'end_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        $this->assertPurged(['campaigns', 'campaign:c1'], ['/campaigns/c1']);
    }

    public function test_bundle_save_purges_frontend(): void
    {
        $campaign = Campaign::create([
            'name' => 'C', 'slug' => 'c2',
            'start_at' => now(), 'end_at' => now()->addDays(7), 'is_active' => true,
        ]);
        $this->purgeCalls = [];

        Bundle::create([
            'campaign_id' => $campaign->id,
            'name' => '組合', 'slug' => 'b1', 'value_price' => 1000,
        ]);

        $this->assertPurged(
            ['bundles', 'bundle:b1', 'campaign:c2'],
            ['/bundles/b1', '/campaigns/c2'],
        );
    }

    public function test_popup_save_purges_frontend(): void
    {
        Popup::create([
            'title' => '彈窗', 'is_active' => true, 'display_frequency' => 'once',
        ]);

        $this->assertPurged(['popups'], ['/']);
    }

    public function test_product_category_save_purges_frontend(): void
    {
        ProductCategory::create(['name' => '分類', 'slug' => 'cat1']);

        $this->assertPurged(
            ['product-categories', 'products'],
            ['/products/category/cat1'],
        );
    }

    public function test_article_category_save_purges_frontend(): void
    {
        ArticleCategory::create(['name' => '分類', 'slug' => 'ac1', 'type' => 'blog']);

        $this->assertPurged(['articles'], ['/articles']);
    }

    public function test_review_save_purges_frontend(): void
    {
        $product = Product::create([
            'name' => 'P', 'slug' => 'p9', 'price' => 100, 'is_active' => true,
        ]);
        $this->purgeCalls = [];

        Review::create([
            'product_id' => $product->id,
            'rating' => 5,
            'content' => '好',
            'reviewer_name' => 'A',
            'is_visible' => true,
        ]);

        $this->assertPurged(
            ['reviews', 'reviews:p9'],
            ['/products/p9'],
        );
    }
}
