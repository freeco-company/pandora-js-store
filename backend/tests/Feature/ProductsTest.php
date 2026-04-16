<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_active_products(): void
    {
        Product::create(['name' => 'Alpha', 'slug' => 'alpha', 'price' => 100, 'is_active' => true]);
        Product::create(['name' => 'Hidden', 'slug' => 'hidden', 'price' => 100, 'is_active' => false]);

        $res = $this->getJson('/api/products');

        $res->assertOk()->assertJsonCount(1);
        $this->assertSame('Alpha', $res->json()[0]['name']);
    }

    public function test_index_filters_by_keyword(): void
    {
        Product::create(['name' => 'Xianxian', 'slug' => 'a', 'price' => 100, 'is_active' => true]);
        Product::create(['name' => 'Probiotic', 'slug' => 'b', 'price' => 100, 'is_active' => true]);

        $res = $this->getJson('/api/products?q=Xian');

        $res->assertOk()->assertJsonCount(1);
    }

    public function test_index_filters_by_category(): void
    {
        $cat = ProductCategory::create(['name' => '美容', 'slug' => 'beauty']);
        $p1 = Product::create(['name' => 'A', 'slug' => 'a', 'price' => 100, 'is_active' => true]);
        Product::create(['name' => 'B', 'slug' => 'b', 'price' => 100, 'is_active' => true]);
        $p1->categories()->attach($cat);

        $res = $this->getJson('/api/products?category=beauty');

        $res->assertOk()->assertJsonCount(1);
    }

    public function test_show_returns_product_by_slug(): void
    {
        Product::create(['name' => 'Alpha', 'slug' => 'alpha-product', 'price' => 100, 'is_active' => true]);

        $res = $this->getJson('/api/products/alpha-product');

        $res->assertOk()->assertJsonPath('slug', 'alpha-product');
    }

    public function test_show_404_for_unknown_slug(): void
    {
        $this->getJson('/api/products/nope')->assertNotFound();
    }

    public function test_show_excludes_inactive(): void
    {
        Product::create(['name' => 'Hidden', 'slug' => 'hidden', 'price' => 100, 'is_active' => false]);

        $this->getJson('/api/products/hidden')->assertNotFound();
    }

    public function test_categories_includes_product_count(): void
    {
        $cat = ProductCategory::create(['name' => 'X', 'slug' => 'x']);
        $p = Product::create(['name' => 'A', 'slug' => 'a', 'price' => 100, 'is_active' => true]);
        $p->categories()->attach($cat);

        $res = $this->getJson('/api/product-categories');

        $res->assertOk()->assertJsonCount(1);
        $this->assertSame(1, $res->json()[0]['products_count']);
    }
}
