<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Hello',
            'slug' => 'hello-' . uniqid(),
            'source_type' => 'blog',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_index_paginates_published_articles(): void
    {
        $this->article();
        $this->article(['status' => 'draft']);

        $res = $this->getJson('/api/articles');

        $res->assertOk();
        $this->assertCount(1, $res->json('data'));
    }

    public function test_index_filters_by_source_type(): void
    {
        $this->article(['source_type' => 'news']);
        $this->article(['source_type' => 'blog']);

        $res = $this->getJson('/api/articles?type=news');

        $this->assertCount(1, $res->json('data'));
    }

    public function test_index_filters_by_comma_separated_types(): void
    {
        $this->article(['source_type' => 'blog']);
        $this->article(['source_type' => 'news']);
        $this->article(['source_type' => 'brand']);
        $this->article(['source_type' => 'recommend']);

        $res = $this->getJson('/api/articles?type=blog,news');

        $this->assertCount(2, $res->json('data'));
        $types = array_column($res->json('data'), 'source_type');
        sort($types);
        $this->assertSame(['blog', 'news'], $types);
    }

    public function test_index_all_when_no_type_filter(): void
    {
        $this->article(['source_type' => 'blog']);
        $this->article(['source_type' => 'brand']);

        $res = $this->getJson('/api/articles');

        $this->assertCount(2, $res->json('data'));
    }

    public function test_show_returns_404_for_expired_promo_article(): void
    {
        $this->article(['slug' => 'expired-promo', 'source_type' => 'news', 'promo_ends_at' => now()->subDay()]);
        $this->article(['slug' => 'live-promo', 'source_type' => 'news', 'promo_ends_at' => now()->addDay()]);

        $this->getJson('/api/articles/expired-promo')->assertNotFound();
        $this->getJson('/api/articles/live-promo')->assertOk();
    }

    public function test_index_hides_expired_promo_articles(): void
    {
        $this->article(['source_type' => 'news', 'title' => 'Live promo']);
        $this->article(['source_type' => 'news', 'title' => 'Expired promo', 'promo_ends_at' => now()->subDay()]);
        $this->article(['source_type' => 'news', 'title' => 'Future promo', 'promo_ends_at' => now()->addDay()]);

        $res = $this->getJson('/api/articles?type=news');

        $titles = array_column($res->json('data'), 'title');
        $this->assertContains('Live promo', $titles);
        $this->assertContains('Future promo', $titles);
        $this->assertNotContains('Expired promo', $titles);
    }

    public function test_index_filters_by_category(): void
    {
        $cat = ArticleCategory::create(['name' => '最新消息', 'slug' => 'news', 'type' => 'news']);
        $a1 = $this->article();
        $this->article();
        $a1->categories()->attach($cat);

        $res = $this->getJson('/api/articles?category=news');

        $this->assertCount(1, $res->json('data'));
    }

    public function test_show_returns_published_article(): void
    {
        $a = $this->article(['slug' => 'abc']);

        $res = $this->getJson('/api/articles/abc');

        $res->assertOk()->assertJsonPath('slug', 'abc');
    }

    public function test_show_404_for_draft(): void
    {
        $this->article(['slug' => 'draft-one', 'status' => 'draft']);

        $this->getJson('/api/articles/draft-one')->assertNotFound();
    }

    public function test_categories_endpoint(): void
    {
        ArticleCategory::create(['name' => 'News', 'slug' => 'news', 'type' => 'news']);
        ArticleCategory::create(['name' => 'Blog', 'slug' => 'blog', 'type' => 'blog']);

        $res = $this->getJson('/api/article-categories');
        $res->assertOk()->assertJsonCount(2);

        $filtered = $this->getJson('/api/article-categories?type=news');
        $filtered->assertJsonCount(1);
    }
}
