<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('streak_days')->default(0);
            $table->date('last_active_date')->nullable();
            $table->string('current_outfit', 32)->nullable();
            $table->string('current_backdrop', 32)->nullable();
            $table->timestamp('last_serendipity_at')->nullable();
            $table->json('activation_progress')->nullable();
            $table->integer('total_spent')->default(0);
            $table->integer('total_orders')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'streak_days', 'last_active_date', 'current_outfit',
                'current_backdrop', 'last_serendipity_at', 'activation_progress',
                'total_spent', 'total_orders',
            ]);
        });
    }
};
