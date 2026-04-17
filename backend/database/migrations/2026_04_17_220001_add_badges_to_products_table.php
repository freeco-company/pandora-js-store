<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // JSON array of badge codes, e.g. ["snq","monde_selection","clean_label"]
            $table->json('badges')->nullable()->after('hf_cert_claim');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('badges');
        });
    }
};
