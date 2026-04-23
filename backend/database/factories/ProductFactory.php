<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Minimal product factory — only the NOT NULL columns. Tests that need
 * richer product data should ->state() it explicitly.
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(2, true);
        return [
            'name'  => $name,
            'slug'  => Str::slug($name) . '-' . Str::random(6),
            'price' => fake()->numberBetween(100, 5000),
            'is_active' => true,
        ];
    }
}
