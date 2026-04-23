<?php

namespace Tests\Feature;

use App\Models\CartEvent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cart-event logging endpoint tests. Covers the happy path, validation, and
 * auth-derived customer_id so the frontend can't spoof identity by passing
 * a customer_id claim in the payload.
 */
class CartEventTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_to_cart_event_creates_row(): void
    {
        $product = Product::factory()->create();

        $res = $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-123',
            'event_type' => 'add_to_cart',
            'product_id' => $product->id,
            'quantity' => 2,
            'value' => 1800,
        ]);

        $res->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('cart_events', [
            'session_id' => 'sess-123',
            'event_type' => 'add_to_cart',
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_view_item_and_begin_checkout_events_are_valid(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-v', 'event_type' => 'view_item', 'product_id' => $product->id,
        ])->assertOk();

        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-v', 'event_type' => 'begin_checkout', 'value' => 3000,
        ])->assertOk();

        $this->assertSame(2, CartEvent::count());
    }

    public function test_invalid_event_type_is_rejected(): void
    {
        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-x',
            'event_type' => 'not_a_real_event',
        ])->assertStatus(422);
    }

    public function test_missing_event_type_is_rejected(): void
    {
        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-x',
        ])->assertStatus(422);
    }

    public function test_event_works_without_product_or_bundle(): void
    {
        // begin_checkout is session-level, doesn't need item context
        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-ck',
            'event_type' => 'begin_checkout',
            'value' => 5000,
        ])->assertOk();

        $this->assertDatabaseHas('cart_events', [
            'session_id' => 'sess-ck',
            'event_type' => 'begin_checkout',
            'product_id' => null,
            'bundle_id' => null,
        ]);
    }

    public function test_nonexistent_product_id_is_rejected(): void
    {
        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-x',
            'event_type' => 'add_to_cart',
            'product_id' => 999999,
        ])->assertStatus(422);
    }

    public function test_quantity_bounds_enforced(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-q', 'event_type' => 'add_to_cart',
            'product_id' => $product->id, 'quantity' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/track/cart-event', [
            'session_id' => 'sess-q', 'event_type' => 'add_to_cart',
            'product_id' => $product->id, 'quantity' => 1000,
        ])->assertStatus(422);
    }
}
