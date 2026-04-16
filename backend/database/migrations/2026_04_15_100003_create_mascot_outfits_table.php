<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mascot_outfits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->timestamp('unlocked_at')->useCurrent();

            $table->unique(['customer_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mascot_outfits');
    }
};
