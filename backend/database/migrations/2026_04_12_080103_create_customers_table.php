<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('phone', 50)->nullable();
            $table->string('password');
            $table->boolean('is_vip')->default(false);
            $table->string('address_city', 100)->nullable();
            $table->string('address_district', 100)->nullable();
            $table->string('address_detail', 500)->nullable();
            $table->string('address_zip', 10)->nullable();
            $table->integer('wp_user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
