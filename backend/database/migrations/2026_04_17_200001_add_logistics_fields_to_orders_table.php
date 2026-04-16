<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ecpay_logistics_id', 32)->nullable()->after('ecpay_trade_no');
            $table->string('cvs_payment_no', 32)->nullable()->after('ecpay_logistics_id');
            $table->string('cvs_validation_no', 16)->nullable()->after('cvs_payment_no');
            $table->string('booking_note', 32)->nullable()->after('cvs_validation_no');
            $table->string('logistics_status_msg', 255)->nullable()->after('booking_note');
            $table->timestamp('logistics_created_at')->nullable()->after('logistics_status_msg');

            $table->index('ecpay_logistics_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['ecpay_logistics_id']);
            $table->dropColumn([
                'ecpay_logistics_id',
                'cvs_payment_no',
                'cvs_validation_no',
                'booking_note',
                'logistics_status_msg',
                'logistics_created_at',
            ]);
        });
    }
};
