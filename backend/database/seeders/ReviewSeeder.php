<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReviewSeeder extends Seeder
{
    /**
     * Seed realistic-looking reviews for all active products.
     * - All 5 stars
     * - Most reviews have no text (~70%)
     * - Some have short, natural-sounding positive comments
     * - Names are masked: 王*明, 陳*, L***e@gmail.com, etc.
     */
    public function run(): void
    {
        // Common Taiwanese surnames + given names for generating realistic masked names
        $maskedNames = [
            '陳*婷', '林*如', '王*萱', '黃*琪', '張*雯',
            '李*芳', '吳*瑩', '劉*君', '蔡*玲', '楊*慧',
            '許*華', '鄭*娟', '謝*珊', '洪*琴', '邱*蓉',
            '廖*筠', '曾*涵', '賴*妤', '徐*恩', '周*儀',
            '趙*穎', '簡*文', '蕭*潔', '葉*真', '呂*宜',
            '施*安', '彭*欣', '何*青', '沈*伶', '方*宇',
            '高*萍', '潘*雅', '傅*怡', '范*靜', '馬*媛',
            '翁*容', '游*珮', '戴*茹', '丁*瑜', '魏*蘭',
            '朱*純', '柯*秀', '鐘*惠', '余*鳳', '盧*芬',
            // Email-style masked names
            'a***e@gmail.com', 'c***y@yahoo.com.tw', 'l***a@gmail.com',
            's***n@hotmail.com', 'j***y@gmail.com', 'm***i@icloud.com',
            'h***g@gmail.com', 'w***n@yahoo.com.tw', 'p***y@gmail.com',
            'y***a@gmail.com', 'r***e@outlook.com', 't***a@gmail.com',
            'k***o@gmail.com', 'b***y@yahoo.com.tw', 'f***n@gmail.com',
        ];

        // Short, natural-sounding positive comments (Traditional Chinese, casual tone)
        // These intentionally vary in length and style to feel authentic
        $comments = [
            // 纖體/代謝類
            '吃了一個月有感',
            '回購第三次了',
            '朋友推薦的 真的不錯',
            '搭配運動效果很好',
            '口感比想像中好喝',
            '方便攜帶 出差也能吃',
            '持續回購中～',
            '比之前吃的牌子好',
            '第一次買 會再回購',
            '味道OK 會持續吃',
            '已經推薦給同事了',
            '很滿意！',
            '效果不錯喔',
            '老公也跟著吃',
            '蠻推的',
            '讚',
            '包裝很好 收到很開心',
            '出貨超快',
            '很喜歡這個味道',
            '甜度剛好不會太甜',

            // 美容/保養類
            '敷完皮膚很水嫩',
            '用了一週感覺有亮',
            '質地很舒服',
            '吸收很快 不黏膩',
            '洗完很清爽',
            '味道很舒服～',
            '每天都在用',
            '送朋友她也很喜歡',

            // 保健類
            '全家都在吃',
            '小朋友也喜歡這個味道',
            '每天一包很方便',
            '買給爸媽吃的',
            '口感好入口',
        ];

        $products = Product::where('is_active', true)->get();

        foreach ($products as $product) {
            // Each product gets 5-25 reviews, skewed toward more popular products
            $reviewCount = rand(5, 25);

            // Shuffle names for this product to avoid repeats
            $availableNames = $maskedNames;
            shuffle($availableNames);

            for ($i = 0; $i < $reviewCount; $i++) {
                $name = $availableNames[$i % count($availableNames)];

                // ~70% no content, ~30% have a short comment
                $content = null;
                if (rand(1, 100) <= 30) {
                    $content = $comments[array_rand($comments)];
                }

                // Random date within last 6 months, more recent reviews more likely
                $daysAgo = $this->weightedRandomDays(1, 180);
                $createdAt = Carbon::now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59));

                Review::create([
                    'product_id' => $product->id,
                    'customer_id' => null,
                    'order_id' => null,
                    'rating' => 5,
                    'content' => $content,
                    'reviewer_name' => $name,
                    'is_verified_purchase' => (bool) rand(0, 1), // randomly mark some as verified
                    'is_seeded' => true,
                    'is_visible' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }

        $total = Review::where('is_seeded', true)->count();
        $this->command->info("Seeded {$total} reviews across {$products->count()} products.");
    }

    /**
     * Weighted random: more likely to return smaller numbers (more recent dates).
     */
    private function weightedRandomDays(int $min, int $max): int
    {
        // Square root distribution — biases toward recent
        $r = mt_rand() / mt_getrandmax();
        return (int) round($min + ($max - $min) * ($r * $r));
    }
}
