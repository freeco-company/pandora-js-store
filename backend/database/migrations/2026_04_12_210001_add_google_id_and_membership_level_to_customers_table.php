<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('email')->index();
            $table->string('membership_level')->default('regular')->after('is_vip');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropColumn(['google_id', 'membership_level']);
        });
    }
};
