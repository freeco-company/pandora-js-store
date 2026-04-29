<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-008 §2.2 段 1 訊號 — franchise_consultations 接 「諮詢加盟」表單。
 *
 * Persists every contact-us submission so business team can follow up even
 * when the original page session is gone. Linked to pandora_user_uuid
 * (nullable) so anonymous landing-page submissions also work; py-service's
 * `loyalist → applicant` lifecycle rule fires only when uuid is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchise_consultations', function (Blueprint $table) {
            $table->id();
            $table->string('pandora_user_uuid', 64)->nullable()->index();
            $table->string('name', 100);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('source', 64)->nullable()->comment('e.g. homepage_banner / pandora_meal_paywall / academy');
            $table->text('note')->nullable();
            // Follow-up state — admin updates as the lead is worked
            $table->enum('status', ['new', 'contacted', 'qualified', 'closed'])->default('new')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_consultations');
    }
};
