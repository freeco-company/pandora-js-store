<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign → (many) Bundle refactor.
 *
 * Before: campaign has ONE implicit bundle (buy/gift items attached
 * directly to campaign via `campaign_product` pivot).
 *
 * After: campaign is a wrapper (name, dates, hero copy); each campaign
 * can have multiple `bundles`, each with its own name, slug, image,
 * buy items, gift items.
 *
 * URL: /bundles/{bundle-slug} — flat, SEO-optimal (keyword weight stays
 * concentrated on the bundle page).
 *
 * Migration strategy: seed a single Bundle per existing Campaign that has
 * any attached products, named after the Campaign itself, and move the
 * old `campaign_product` rows into the new `bundle_product` table keyed
 * by bundle_id. Then drop the old table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['campaign_id', 'sort_order']);
        });

        Schema::create('bundle_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('role', 8); // 'buy' | 'gift'
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bundle_id', 'product_id', 'role']);
        });

        // ── Data migration ──────────────────────────────────────
        // For each existing Campaign that has attached products, create
        // a default Bundle and move the pivot rows. Campaigns without
        // any attached products stay empty (admin fills bundles later).
        $campaigns = DB::table('campaigns')->get();
        foreach ($campaigns as $campaign) {
            $rows = DB::table('campaign_product')
                ->where('campaign_id', $campaign->id)
                ->get();
            if ($rows->isEmpty()) continue;

            $bundleId = DB::table('bundles')->insertGetId([
                'campaign_id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug . '-default',
                'description' => $campaign->description,
                'image' => $campaign->image,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($rows as $row) {
                DB::table('bundle_product')->insert([
                    'bundle_id' => $bundleId,
                    'product_id' => $row->product_id,
                    'role' => $row->role ?? 'buy',
                    'quantity' => $row->quantity ?? 1,
                    'sort_order' => $row->sort_order ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('campaign_product');
    }

    public function down(): void
    {
        Schema::create('campaign_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('role', 8)->default('buy');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('campaign_price', 10, 2)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'product_id', 'role']);
        });

        // Restore pivot rows from bundle_product (best-effort — loses
        // the multi-bundle structure, collapses to the first bundle).
        $bundles = DB::table('bundles')->get();
        foreach ($bundles as $bundle) {
            $rows = DB::table('bundle_product')->where('bundle_id', $bundle->id)->get();
            foreach ($rows as $row) {
                DB::table('campaign_product')->insertOrIgnore([
                    'campaign_id' => $bundle->campaign_id,
                    'product_id' => $row->product_id,
                    'role' => $row->role,
                    'quantity' => $row->quantity,
                    'sort_order' => $row->sort_order,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('bundle_product');
        Schema::dropIfExists('bundles');
    }
};
