<?php

namespace App\Services;

class AchievementCatalog
{
    // Core onboarding
    public const FIRST_BROWSE = 'first_browse';
    public const FIRST_ARTICLE = 'first_article';
    public const FIRST_BRAND = 'first_brand';
    public const FIRST_CART = 'first_cart';
    public const FIRST_MASCOT = 'first_mascot';
    public const FIRST_ORDER = 'first_order';

    // Repeat purchase
    public const ORDER_3 = 'order_3';
    public const ORDER_5 = 'order_5';
    public const ORDER_10 = 'order_10';

    // Spending milestones
    public const SPEND_1K = 'spend_1k';
    public const SPEND_5K = 'spend_5k';
    public const SPEND_10K = 'spend_10k';

    // Tier discovery
    public const UNLOCK_COMBO = 'unlock_combo';      // first combo-tier order
    public const UNLOCK_VIP = 'unlock_vip';          // first VIP-tier order
    public const VIP_3 = 'vip_3';                    // 3 VIP-tier orders

    // Category exploration
    public const EXPLORE_SLIMMING = 'explore_slimming';
    public const EXPLORE_HEALTH = 'explore_health';
    public const EXPLORE_BEAUTY = 'explore_beauty';
    public const EXPLORE_ALL = 'explore_all';

    // Reviews
    public const FIRST_REVIEW = 'first_review';

    // Engagement
    public const FIRST_COUPON = 'first_coupon';
    public const STREAK_7 = 'streak_7';
    public const STREAK_30 = 'streak_30';
    public const STREAK_100 = 'streak_100';

    // Referral
    public const FIRST_REFERRAL = 'first_referral';
    public const REFERRAL_3 = 'referral_3';
    public const REFERRAL_10 = 'referral_10';
    public const FIRST_REFERRED = 'first_referred';

    /**
     * Display metadata + optional progress definition.
     *
     * Progress shape: ['type' => string, 'target' => int]
     * Progress types — must match keys in AchievementProgressCalculator:
     *   order_count        — completed orders
     *   spend_total        — sum(orders.total)
     *   vip_order_count    — orders with pricing_tier='vip'
     *   streak_days        — current consecutive-visit streak
     *   referral_count     — successful referrals
     *   category_count     — distinct product categories ever purchased
     *
     * Achievements without a progress key are binary (you either did it or you didn't)
     * and the UI just shows locked/unlocked.
     */
    public static function all(): array
    {
        return [
            self::FIRST_BROWSE => ['emoji' => 'shopping-bag', 'name' => '好奇仙女', 'description' => '逛了第一件商品', 'tier' => 'bronze'],
            self::FIRST_ARTICLE => ['emoji' => 'book', 'name' => '閱讀仙女', 'description' => '看了第一篇仙女誌', 'tier' => 'bronze'],
            self::FIRST_BRAND => ['emoji' => 'cherry-blossom', 'name' => '知心仙女', 'description' => '認識 FP 團隊', 'tier' => 'bronze'],
            self::FIRST_CART => ['emoji' => 'cart', 'name' => '心動瞬間', 'description' => '加入第一件商品到購物車', 'tier' => 'bronze'],
            self::FIRST_MASCOT => ['emoji' => 'sprout', 'name' => '芽芽之友', 'description' => '進入芽芽之家', 'tier' => 'bronze'],
            self::FIRST_ORDER => ['emoji' => 'party', 'name' => '首購達成', 'description' => '完成第一筆訂單', 'tier' => 'silver'],

            self::ORDER_3 => ['emoji' => 'cherry-blossom', 'name' => '回頭仙女', 'description' => '累積 3 筆訂單', 'tier' => 'silver',
                              'progress' => ['type' => 'order_count', 'target' => 3]],
            self::ORDER_5 => ['emoji' => 'star', 'name' => '熟客仙女', 'description' => '累積 5 筆訂單', 'tier' => 'silver',
                              'progress' => ['type' => 'order_count', 'target' => 5]],
            self::ORDER_10 => ['emoji' => 'diamond', 'name' => '鐵粉仙女', 'description' => '累積 10 筆訂單', 'tier' => 'gold',
                               'progress' => ['type' => 'order_count', 'target' => 10]],

            self::SPEND_1K => ['emoji' => 'money-bag', 'name' => '千元俱樂部', 'description' => '累積消費滿 NT$1,000', 'tier' => 'bronze',
                               'progress' => ['type' => 'spend_total', 'target' => 1000]],
            self::SPEND_5K => ['emoji' => 'diamond', 'name' => '五千達人', 'description' => '累積消費滿 NT$5,000', 'tier' => 'silver',
                               'progress' => ['type' => 'spend_total', 'target' => 5000]],
            self::SPEND_10K => ['emoji' => 'crown', 'name' => '萬元貴賓', 'description' => '累積消費滿 NT$10,000', 'tier' => 'gold',
                                'progress' => ['type' => 'spend_total', 'target' => 10000]],

            self::UNLOCK_COMBO => ['emoji' => 'ribbon-bow', 'name' => '組合解鎖', 'description' => '首次使用組合價下單', 'tier' => 'bronze'],
            self::UNLOCK_VIP => ['emoji' => 'sparkle', 'name' => 'VIP 解鎖', 'description' => '首次觸發 VIP 價下單', 'tier' => 'silver'],
            self::VIP_3 => ['emoji' => 'trophy', 'name' => 'VIP 常客', 'description' => '累積 3 筆 VIP 價訂單', 'tier' => 'gold',
                            'progress' => ['type' => 'vip_order_count', 'target' => 3]],

            self::EXPLORE_SLIMMING => ['emoji' => 'leaf', 'name' => '纖體探索', 'description' => '購買體重管理商品', 'tier' => 'bronze'],
            self::EXPLORE_HEALTH => ['emoji' => 'herb', 'name' => '保健探索', 'description' => '購買健康保健商品', 'tier' => 'bronze'],
            self::EXPLORE_BEAUTY => ['emoji' => 'hibiscus', 'name' => '美容探索', 'description' => '購買美容保養商品', 'tier' => 'bronze'],
            self::EXPLORE_ALL => ['emoji' => 'rainbow', 'name' => '全品類達人', 'description' => '三大品類皆有購買', 'tier' => 'gold',
                                  'progress' => ['type' => 'category_count', 'target' => 3]],

            self::FIRST_REVIEW => ['emoji' => 'clipboard', 'name' => '首評達成', 'description' => '留下第一則商品評論', 'tier' => 'silver'],

            self::FIRST_COUPON => ['emoji' => 'gift', 'name' => '省錢仙女', 'description' => '首次使用優惠碼', 'tier' => 'bronze'],
            self::STREAK_7 => ['emoji' => 'fire', 'name' => '七日連訪', 'description' => '連續 7 天造訪', 'tier' => 'silver',
                               'progress' => ['type' => 'streak_days', 'target' => 7]],
            self::STREAK_30 => ['emoji' => 'fire', 'name' => '月月相伴', 'description' => '連續 30 天造訪', 'tier' => 'gold',
                                'progress' => ['type' => 'streak_days', 'target' => 30]],
            self::STREAK_100 => ['emoji' => 'star', 'name' => '百日傳說', 'description' => '連續 100 天造訪', 'tier' => 'gold',
                                 'progress' => ['type' => 'streak_days', 'target' => 100]],

            self::FIRST_REFERRAL => ['emoji' => 'gift', 'name' => '第一位推薦者', 'description' => '成功邀請一位朋友完成首單', 'tier' => 'silver'],
            self::REFERRAL_3     => ['emoji' => 'handshake', 'name' => '仙女推廣大使', 'description' => '累積推薦 3 位朋友', 'tier' => 'gold',
                                     'progress' => ['type' => 'referral_count', 'target' => 3]],
            self::REFERRAL_10    => ['emoji' => 'crown', 'name' => '仙女 KOL', 'description' => '累積推薦 10 位朋友', 'tier' => 'gold',
                                     'progress' => ['type' => 'referral_count', 'target' => 10]],
            self::FIRST_REFERRED => ['emoji' => 'cherry-blossom', 'name' => '被邀請的仙女', 'description' => '透過朋友推薦碼加入', 'tier' => 'bronze'],
        ];
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }
}
