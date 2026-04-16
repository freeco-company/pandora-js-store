<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_sanitizes_description_and_appends_disclaimer(): void
    {
        $p = Product::create([
            'name' => '100% 治療頂級',
            'slug' => 'p1',
            'price' => 1000,
            'description' => '<p>特效減肥 100% 有效</p>',
            'is_active' => true,
        ]);

        $this->assertStringNotContainsString('特效', $p->description);
        $this->assertStringNotContainsString('減肥', $p->description);
        $this->assertStringContainsString('legal-disclaimer', $p->description);
        $this->assertStringNotContainsString('治療', $p->name);
        $this->assertStringNotContainsString('100%', $p->name);
    }

    public function test_product_update_resanitizes_dirty_fields(): void
    {
        $p = Product::create([
            'name' => '乾淨商品',
            'slug' => 'p2',
            'price' => 500,
            'description' => '<p>正常說明</p>',
            'is_active' => true,
        ]);

        $p->description = '<p>絕對保證根治</p>';
        $p->save();

        $this->assertStringNotContainsString('絕對', $p->description);
        $this->assertStringNotContainsString('保證', $p->description);
        $this->assertStringNotContainsString('根治', $p->description);
    }

    public function test_product_save_is_idempotent(): void
    {
        $p = Product::create([
            'name' => 'X',
            'slug' => 'p3',
            'price' => 500,
            'description' => '<p>100% 天然</p>',
            'is_active' => true,
        ]);
        $first = $p->description;
        $p->save();
        $p->save();
        // Description unchanged across repeat saves; one disclaimer block
        $this->assertSame($first, $p->fresh()->description);
        $this->assertSame(1, substr_count($p->fresh()->description, 'legal-disclaimer'));
    }

    public function test_article_save_sanitizes_content_title_excerpt(): void
    {
        $a = Article::create([
            'title' => '100% 治癒秘笈',
            'slug' => 'a1',
            'source_type' => 'blog',
            'status' => 'published',
            'published_at' => now(),
            'content' => '<p>立即見效的減肥方法</p>',
            'excerpt' => '神效速成',
        ]);

        $this->assertStringNotContainsString('100%', $a->title);
        $this->assertStringNotContainsString('治癒', $a->title);
        $this->assertStringNotContainsString('立即見效', $a->content);
        $this->assertStringNotContainsString('減肥', $a->content);
        $this->assertStringNotContainsString('神效', $a->excerpt);
        $this->assertStringContainsString('legal-disclaimer', $a->content);
    }
}
