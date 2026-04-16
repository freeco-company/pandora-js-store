<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-customer address book. Used at checkout as a saved-address picker
 * and for quick re-ordering from the account area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();       // 家 / 公司 / 父母家
            $table->string('recipient_name', 100);
            $table->string('phone', 30);
            $table->string('postal_code', 10)->nullable();
            $table->string('city', 40)->nullable();
            $table->string('district', 40)->nullable();
            $table->string('street', 255);                 // 剩餘門牌
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
