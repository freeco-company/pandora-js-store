<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 衛福部健康食品認證字號（例：衛部健食字第 A00455 號）
            $table->string('hf_cert_no', 64)->nullable()->after('sku');
            // 經核可保健功效（例：輔助調節血脂、免疫調節）
            $table->string('hf_cert_claim', 255)->nullable()->after('hf_cert_no');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['hf_cert_no', 'hf_cert_claim']);
        });
    }
};
