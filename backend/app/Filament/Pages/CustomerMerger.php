<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Services\CustomerMergeService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * 後台「重複會員偵測 + 人工合併」頁。
 *
 * 顯示 \App\Services\CustomerMergeService::detectDuplicates() 的所有候選對，
 * 並排兩個 customer 的關鍵欄位 + 訂單數 + 累積消費，admin 可一鍵：
 *   - 合併 A→B / B→A（會 capture 哪個是 surviving）
 *   - 標記「不是同一人」（寫入 customer_merge_dismissed，下次掃描跳過）
 */
class CustomerMerger extends Page
{
    protected string $view = 'filament.pages.customer-merger';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | UnitEnum | null $navigationGroup = '客戶管理';
    protected static ?string $navigationLabel = '重複會員偵測';
    protected static ?string $title = '重複會員偵測與人工合併';
    protected static ?int $navigationSort = 99;

    /** @var array<int,array<string,mixed>> */
    public array $candidates = [];

    public function mount(): void
    {
        $this->loadCandidates();
    }

    public function loadCandidates(): void
    {
        $service = app(CustomerMergeService::class);
        $raw = $service->detectDuplicates();

        // 序列化成 view 友善格式，附上 surviving 建議
        $this->candidates = array_map(function ($c) use ($service) {
            $picked = $service->pickSurvivor($c['customer_a'], $c['customer_b']);
            return [
                'a' => $this->summarize($c['customer_a']),
                'b' => $this->summarize($c['customer_b']),
                'confidence' => $c['confidence'],
                'reason' => $c['reason'],
                'recommended_surviving_id' => $picked['surviving']->id,
            ];
        }, $raw);
    }

    private function summarize(Customer $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'google_id' => $c->google_id,
            'line_id' => $c->line_id,
            'is_placeholder_email' => str_ends_with((string) $c->email, '@line.user'),
            'total_orders' => (int) $c->total_orders,
            'total_spent' => (int) $c->total_spent,
            'last_active_date' => $c->last_active_date?->format('Y-m-d'),
            'created_at' => $c->created_at?->format('Y-m-d'),
        ];
    }

    /**
     * Merge: keep `surviving_id`, absorb the other.
     * Both IDs sent from the form — we pick which is surviving by the radio.
     */
    public function merge(int $a_id, int $b_id, int $surviving_id): void
    {
        if (!in_array($surviving_id, [$a_id, $b_id], true)) {
            $this->errorNotify('參數錯誤', 'surviving_id 必須是 a 或 b 其中之一');
            return;
        }

        $absorbedId = $surviving_id === $a_id ? $b_id : $a_id;
        $surviving = Customer::find($surviving_id);
        $absorbed = Customer::find($absorbedId);

        if (!$surviving || !$absorbed) {
            $this->errorNotify('找不到 customer', '可能已被合併或刪除');
            $this->loadCandidates();
            return;
        }

        try {
            $stats = app(CustomerMergeService::class)->merge(
                surviving: $surviving,
                absorbed: $absorbed,
                reason: 'manual:filament',
                actorAdminId: Auth::id(),
            );

            Notification::make()
                ->title('合併成功 ✓')
                ->body(sprintf(
                    'absorbed #%d → surviving #%d（orders: %d、addresses: %d）',
                    $absorbedId, $surviving_id,
                    $stats['orders'] ?? 0, $stats['customer_addresses'] ?? 0
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            $this->errorNotify('合併失敗', $e->getMessage());
        }

        $this->loadCandidates();
    }

    public function dismiss(int $a_id, int $b_id, ?string $note = null): void
    {
        $smaller = min($a_id, $b_id);
        $larger = max($a_id, $b_id);

        DB::table('customer_merge_dismissed')->updateOrInsert(
            ['customer_a_id' => $smaller, 'customer_b_id' => $larger],
            [
                'reason' => $note ?: '不是同一人',
                'actor_admin_id' => Auth::id(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Notification::make()
            ->title('已標記')
            ->body("#{$smaller} 與 #{$larger} 不會再出現在偵測清單")
            ->success()
            ->send();

        $this->loadCandidates();
    }

    public function refresh(): void
    {
        $this->loadCandidates();
        Notification::make()->title('已重新掃描')->success()->send();
    }

    private function errorNotify(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->danger()->send();
    }
}
