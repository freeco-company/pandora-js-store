<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JerosseProductSeeder extends Seeder
{
    /**
     * Seed JEROSSE catalog (22 products) mapped from 仙女團隊 pricing ladder.
     *
     * Retail tiers: 零售 → regular price, 1+1 → combo price, VIP → vip price.
     * Source: salebox-fairy/backend/database/seeders/FairyTeamSeeder.php
     */
    public function run(): void
    {
        $categories = [
            'slimming' => ['name' => '體重管理', 'sort_order' => 1],
            'health' => ['name' => '健康保健', 'sort_order' => 2],
            'beauty' => ['name' => '美容保養', 'sort_order' => 3],
        ];

        $categoryIds = [];
        foreach ($categories as $slug => $data) {
            $cat = ProductCategory::updateOrCreate(
                ['slug' => $slug],
                ['name' => $data['name'], 'sort_order' => $data['sort_order']]
            );
            $categoryIds[$slug] = $cat->id;
        }

        // [零售, 1+1, VIP, category, slug]
        $products = [
            ['纖纖飲X',      1480, 1380, 1280, 'slimming', 'xian-xian-yin-x'],
            ['纖飄錠',        1480, 1380, 1280, 'slimming', 'xian-piao-ding'],
            ['爆纖錠',        880,  820,  740,  'slimming', 'bao-xian-ding'],
            ['粉體',          250,  220,  200,  'slimming', 'fen-ti'],
            ['纖酵素',        990,  900,  830,  'slimming', 'xian-jiao-su'],
            ['酵體',          180,  165,  150,  'slimming', 'jiao-ti'],
            ['肽纖飲-可可',   990,  900,  830,  'slimming', 'tai-xian-yin-cocoa'],
            ['肽纖飲-奶茶',   990,  900,  830,  'slimming', 'tai-xian-yin-milk-tea'],
            ['雪花紫纖飲',    990,  900,  830,  'slimming', 'xue-hua-zi-xian-yin'],
            ['9國錠',         690,  640,  580,  'slimming', '9-guo-ding'],

            ['葉黃素EX飲',    1080, 1020, 950,  'health',   'ye-huang-su-ex-yin'],
            ['益生菌',        1280, 1180, 1100, 'health',   'yi-sheng-jun'],
            ['療肺草正冠茶',  990,  900,  830,  'health',   'liao-fei-cao-zheng-guan-cha'],
            ['固樂纖',        1680, 1580, 1450, 'health',   'gu-le-xian'],
            ['葉黃素果凍',    690,  640,  580,  'health',   'ye-huang-su-guo-dong'],

            ['水光錠',        1680, 1580, 1450, 'beauty',   'shui-guang-ding'],
            ['水光繃帶面膜',  1280, 1180, 1100, 'beauty',   'shui-guang-beng-dai-mian-mo'],
            ['雪聚露',        1280, 1180, 1100, 'beauty',   'xue-ju-lu'],
            ['婕肌零',        990,  900,  830,  'beauty',   'jie-ji-ling'],
            ['玻尿酸原液',    990,  900,  830,  'beauty',   'bo-niao-suan-yuan-ye'],
            ['身體精華油',    1280, 1180, 1100, 'beauty',   'shen-ti-jing-hua-you'],
        ];

        DB::transaction(function () use ($products, $categoryIds) {
            foreach ($products as $index => [$name, $price, $comboPrice, $vipPrice, $categoryKey, $slug]) {
                $product = Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'short_description' => "{$name} — JEROSSE 婕樂纖正品授權",
                        'description' => "<p>JEROSSE 婕樂纖 {$name}，官方正品授權經銷商販售。</p><p>全館滿 2 件享組合價，組合總額滿 NT$4,000 升級 VIP 價。</p>",
                        'price' => $price,
                        'combo_price' => $comboPrice,
                        'vip_price' => $vipPrice,
                        'stock_quantity' => 99,
                        'stock_status' => 'instock',
                        'is_active' => true,
                        'sort_order' => $index,
                    ]
                );

                $product->categories()->syncWithoutDetaching([$categoryIds[$categoryKey]]);
            }
        });

        $this->command?->info('✓ Seeded ' . count($products) . ' JEROSSE products across 3 categories.');
    }
}
