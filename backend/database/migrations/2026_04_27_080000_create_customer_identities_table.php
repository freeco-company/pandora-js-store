<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * customer_identities — 同一個客戶可以有多個 identity（email / phone /
 * google_id / line_id），新表是 lookup table，customers 表既有欄位作為
 * primary identity cache 不動。
 *
 * 用途：
 *   1. OAuth 登入時可由 (type, value) 反查 customer
 *   2. 結帳合併判斷時可看「這個 phone 屬於哪個 customer」
 *   3. 之後加 Apple / Facebook 登入不用改 schema
 *   4. 給 dedupe command 與後台合併 UI 用作合併目標
 *
 * UNIQUE (type, value)：保證同一個 LINE userId 不會分散在多個 customer，
 * 也預防同一 email 同時是 A 帳號的 primary、又被加成 B 帳號的 alternate。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            // type 用 string 而非 enum，未來新增 provider 不用 ALTER TABLE
            $table->string('type', 24);
            $table->string('value', 255);
            // 為 email 留 verified_at（未來加 email-otp 用），其他 type 從 OAuth 拿到就視為已驗證
            $table->timestamp('verified_at')->nullable();
            // primary = 此 type 在該 customer 的「主要」identity（一般 = customers 表上的快取欄位）
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index(['customer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identities');
    }
};
