<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->decimal('original_value_price', 10, 2)->nullable()->after('value_price');
        });

        DB::statement('UPDATE bundles SET original_value_price = value_price WHERE value_price IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('bundles', function (Blueprint $table) {
            $table->dropColumn('original_value_price');
        });
    }
};
