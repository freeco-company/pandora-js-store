<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 合併兩個 customer：把 absorbed 的所有資料 reparent 到 surviving，
 * 然後把 absorbed 砍掉。整個動作在一個 DB transaction 內完成。
 *
 * 目標：所有 FK 到 customers.id 的表都要 reparent。漏掉一個 = 殘留資料 = bug。
 *
 * 核心難題是 dependent table 的 unique constraint：
 *   wishlists (customer_id, product_id) 唯一 — 兩個 customer 都收藏同 product
 *   時直接 UPDATE customer_id 會炸。對策是先刪掉 absorbed 那邊的衝突 row。
 *
 * 累計欄位（total_orders / total_spent / streak_days）合併採取「safe max/sum」：
 *   - total_orders / total_spent: SUM（兩邊訂單都會 reparent，總數要對齊）
 *   - streak_days: MAX（連續登入取較長者）
 *   - last_active_date: MAX
 */
class CustomerMergeService
{
    /**
     * Tables with `customer_id` FK and any (customer_id, X) unique constraints.
     * 對於有 unique 的 table，merge 前要先把 absorbed 那邊與 surviving 衝突的 row 砍掉。
     *
     * 格式：[table => [unique_partner_columns]]，partner null = 沒 unique 限制可直接 UPDATE
     */
    private const FK_TABLES = [
        'orders' => null,
        'customer_addresses' => null,
        'reviews' => null,
        'visits' => null,
        'cart_events' => null,
        'stock_notifications' => null,
        'customer_identities' => null, // identity 衝突自然刪除（同 (type,value) 已 unique）
        // 有 (customer_id, X) unique 的：
        'wishlists' => ['product_id'],
        'achievements' => ['code'],
        'mascot_outfits' => ['code'],
        'bundle_wishlist_alerts' => ['bundle_id'],
    ];

    /**
     * @param Customer $surviving 留下來的
     * @param Customer $absorbed  被合併、即將砍掉的
     * @param string   $reason    寫進 merge log
     * @param ?int     $actorAdminId Filament 操作者 ID；CLI 自動合併傳 null
     *
     * @return array<string,int> reparent 統計（每張表幾筆）
     */
    public function merge(Customer $surviving, Customer $absorbed, string $reason, ?int $actorAdminId = null): array
    {
        if ($surviving->id === $absorbed->id) {
            throw new \InvalidArgumentException('Cannot merge customer into itself');
        }

        return DB::transaction(function () use ($surviving, $absorbed, $reason, $actorAdminId) {
            $stats = [];

            // 1. Snapshot before merge — for audit
            $snapshot = [
                'surviving' => [
                    'id' => $surviving->id,
                    'email' => $surviving->email,
                    'total_orders' => $surviving->total_orders,
                    'total_spent' => $surviving->total_spent,
                ],
                'absorbed' => [
                    'id' => $absorbed->id,
                    'email' => $absorbed->email,
                    'total_orders' => $absorbed->total_orders,
                    'total_spent' => $absorbed->total_spent,
                ],
            ];

            // 2. Reparent each FK table
            foreach (self::FK_TABLES as $table => $uniquePartners) {
                if (!\Schema::hasTable($table)) continue; // 容忍未來新增/移除 table

                if ($uniquePartners) {
                    // 先刪掉 absorbed 在 (customer_id, X) 上會跟 surviving 衝突的 row
                    $partnerCols = is_array($uniquePartners) ? $uniquePartners : [$uniquePartners];
                    $this->resolveUniqueConflicts($table, $partnerCols, $surviving->id, $absorbed->id);
                }

                $count = DB::table($table)
                    ->where('customer_id', $absorbed->id)
                    ->update(['customer_id' => $surviving->id]);
                $stats[$table] = $count;
            }

            // 3. 自我參照：referred_by_customer_id
            $stats['customers.referred_by'] = DB::table('customers')
                ->where('referred_by_customer_id', $absorbed->id)
                ->update(['referred_by_customer_id' => $surviving->id]);

            // 4. 在更新 surviving 前先把 absorbed 的 identity 欄位 capture 起來，
            //    然後砍 absorbed（釋放 customers.email / customers.line_id unique 限制），
            //    最後才更新 surviving — 避開「同欄位同 unique 值同時存在」的 collision。
            $borrowables = [];
            foreach (['google_id', 'line_id', 'phone'] as $col) {
                if (empty($surviving->{$col}) && !empty($absorbed->{$col})) {
                    $borrowables[$col] = $absorbed->{$col};
                }
            }
            $upgradeEmail = null;
            if (
                str_ends_with((string) $surviving->email, '@line.user')
                && !str_ends_with((string) $absorbed->email, '@line.user')
                && $absorbed->email
            ) {
                $upgradeEmail = $absorbed->email;
            }

            // 5. 寫 merge log（在刪除前，因為要記下 absorbed 各欄位）
            DB::table('customer_merge_log')->insert([
                'surviving_customer_id' => $surviving->id,
                'absorbed_customer_id' => $absorbed->id,
                'absorbed_email' => $absorbed->email,
                'absorbed_phone' => $absorbed->phone,
                'absorbed_google_id' => $absorbed->google_id,
                'absorbed_line_id' => $absorbed->line_id,
                'reason' => $reason,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'actor_admin_id' => $actorAdminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. 砍 absorbed（identities 已 reparent 完成，customer_identities cascade 不會誤刪）
            DB::table('customers')->where('id', $absorbed->id)->delete();

            // 7. 累計欄位合併 + 從 absorbed 借過來的 identity 欄位
            $survivingUpdates = [
                'total_orders' => (int) $surviving->total_orders + (int) $absorbed->total_orders,
                'total_spent' => (float) $surviving->total_spent + (float) $absorbed->total_spent,
                'streak_days' => max((int) $surviving->streak_days, (int) $absorbed->streak_days),
                'last_active_date' => $this->maxDate($surviving->last_active_date, $absorbed->last_active_date),
            ];
            foreach ($borrowables as $col => $val) {
                $survivingUpdates[$col] = $val;
            }
            if ($upgradeEmail) {
                $survivingUpdates['email'] = $upgradeEmail;
            }

            DB::table('customers')->where('id', $surviving->id)->update($survivingUpdates);

            Log::info('[customer.merge] success', [
                'surviving' => $surviving->id,
                'absorbed' => $absorbed->id,
                'reason' => $reason,
                'stats' => $stats,
            ]);

            return $stats;
        });
    }

    private function resolveUniqueConflicts(string $table, array $partnerCols, int $survivingId, int $absorbedId): void
    {
        // 找 absorbed 在這個 table 中、(partner_cols) 已被 surviving 佔用的 row
        $partnerColsList = implode(',', array_map(fn ($c) => "`$c`", $partnerCols));
        $absorbedRows = DB::select(
            "SELECT id, $partnerColsList FROM `$table` WHERE customer_id = ?",
            [$absorbedId]
        );

        foreach ($absorbedRows as $row) {
            $where = ['customer_id' => $survivingId];
            foreach ($partnerCols as $col) {
                $where[$col] = $row->{$col};
            }
            $conflict = DB::table($table)->where($where)->exists();
            if ($conflict) {
                // surviving 已有相同 (customer_id, partner_cols) — 砍掉 absorbed 的這筆
                // 例外：achievements / mascot_outfits 應保留「最早解鎖」的，但 unique 是
                // (customer_id, code)，兩邊解鎖同一個 code 不會有資訊損失（unlocked_at 略有差距）。
                // 此處統一砍 absorbed 的，保留 surviving 的（surviving 是優先方）。
                DB::table($table)->where('id', $row->id)->delete();
            }
        }
    }

    private function maxDate($a, $b): ?string
    {
        if (!$a) return $b instanceof \DateTimeInterface ? $b->format('Y-m-d') : $b;
        if (!$b) return $a instanceof \DateTimeInterface ? $a->format('Y-m-d') : $a;
        $aStr = $a instanceof \DateTimeInterface ? $a->format('Y-m-d') : (string) $a;
        $bStr = $b instanceof \DateTimeInterface ? $b->format('Y-m-d') : (string) $b;
        return $aStr > $bStr ? $aStr : $bStr;
    }

    /**
     * 偵測重複 customer 候選對。回傳 [['customer_a' => ..., 'customer_b' => ..., 'confidence' => 'high|medium|low', 'reason' => '...'], ...]
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectDuplicates(): array
    {
        $candidates = [];
        $seen = [];

        $addPair = function (Customer $a, Customer $b, string $confidence, string $reason) use (&$candidates, &$seen) {
            $key = min($a->id, $b->id) . '_' . max($a->id, $b->id);
            if (isset($seen[$key])) return;
            // 已被 dismiss 的跳過
            $smaller = min($a->id, $b->id);
            $larger = max($a->id, $b->id);
            if (DB::table('customer_merge_dismissed')
                ->where('customer_a_id', $smaller)
                ->where('customer_b_id', $larger)
                ->exists()
            ) return;
            // 已合併過的不會出現（一邊已被砍）但保險起見
            if (!Customer::find($a->id) || !Customer::find($b->id)) return;

            $seen[$key] = true;
            $candidates[] = [
                'customer_a' => $a,
                'customer_b' => $b,
                'confidence' => $confidence,
                'reason' => $reason,
            ];
        };

        // High：phone 相同 + 一邊 email 是 placeholder
        $highRows = DB::select("
            SELECT c1.id AS a_id, c2.id AS b_id
            FROM customers c1
            JOIN customers c2 ON c1.phone = c2.phone AND c1.id < c2.id
            WHERE c1.phone IS NOT NULL AND c1.phone != ''
              AND (
                c1.email LIKE '%@line.user' OR c2.email LIKE '%@line.user'
              )
        ");
        foreach ($highRows as $row) {
            $a = Customer::find($row->a_id);
            $b = Customer::find($row->b_id);
            if ($a && $b) $addPair($a, $b, 'high', 'phone match + 一方 email 為 LINE placeholder');
        }

        // Medium：phone 相同 + 都是真實 email + name 相似
        $mediumRows = DB::select("
            SELECT c1.id AS a_id, c2.id AS b_id, c1.name AS a_name, c2.name AS b_name
            FROM customers c1
            JOIN customers c2 ON c1.phone = c2.phone AND c1.id < c2.id
            WHERE c1.phone IS NOT NULL AND c1.phone != ''
              AND c1.email NOT LIKE '%@line.user'
              AND c2.email NOT LIKE '%@line.user'
        ");
        foreach ($mediumRows as $row) {
            // name 相似度判斷：完全相同 OR Levenshtein < 3（中文也算 byte-level）
            $a = $row->a_name ?? '';
            $b = $row->b_name ?? '';
            if ($a === '' || $b === '') continue;
            $similar = $a === $b || (mb_strlen($a) > 0 && levenshtein(mb_substr($a, 0, 20), mb_substr($b, 0, 20)) < 3);
            if (!$similar) continue;

            $cA = Customer::find($row->a_id);
            $cB = Customer::find($row->b_id);
            if ($cA && $cB) $addPair($cA, $cB, 'medium', 'phone 相同 + name 相似');
        }

        return $candidates;
    }

    /**
     * 從一對候選中決定 surviving / absorbed。規則優先序：
     *   1. 真實 email > placeholder email
     *   2. 訂單較多的留下
     *   3. 較早建立的留下（id 較小）
     *
     * @return array{surviving: Customer, absorbed: Customer}
     */
    public function pickSurvivor(Customer $a, Customer $b): array
    {
        $aPlaceholder = str_ends_with((string) $a->email, '@line.user');
        $bPlaceholder = str_ends_with((string) $b->email, '@line.user');
        if ($aPlaceholder && !$bPlaceholder) return ['surviving' => $b, 'absorbed' => $a];
        if (!$aPlaceholder && $bPlaceholder) return ['surviving' => $a, 'absorbed' => $b];

        if ((int) $a->total_orders !== (int) $b->total_orders) {
            return $a->total_orders > $b->total_orders
                ? ['surviving' => $a, 'absorbed' => $b]
                : ['surviving' => $b, 'absorbed' => $a];
        }

        return $a->id < $b->id
            ? ['surviving' => $a, 'absorbed' => $b]
            : ['surviving' => $b, 'absorbed' => $a];
    }
}
