<?php

namespace App\Services;

use App\Models\Customer;

class SerendipityService
{
    private const COOLDOWN_HOURS = 20;
    private const FIRE_CHANCE = 0.15; // 15%

    /**
     * Maybe generate a serendipitous bubble message for the customer.
     * Returns ['message' => ..., 'emoji' => ...] or null.
     */
    public function maybeGenerate(Customer $customer): ?array
    {
        if ($customer->last_serendipity_at &&
            $customer->last_serendipity_at->diffInHours(now()) < self::COOLDOWN_HOURS) {
            return null;
        }

        if (mt_rand(0, 99) / 100 >= self::FIRE_CHANCE) return null;

        $message = $this->pickMessage($customer);
        if (!$message) return null;

        $customer->update(['last_serendipity_at' => now()]);

        return $message;
    }

    private function pickMessage(Customer $customer): ?array
    {
        $messages = [];

        $hour = now()->hour;
        if ($hour >= 6 && $hour < 10) {
            $messages[] = ['message' => '早安仙女～新的一天來看看有什麼好物吧', 'emoji' => '🌅'];
        } elseif ($hour >= 22 || $hour < 2) {
            $messages[] = ['message' => '夜貓仙女安安，記得早點休息喔', 'emoji' => '🌙'];
        }

        if (in_array(now()->dayOfWeek, [0, 6])) {
            $messages[] = ['message' => '週末愉快～放鬆一下吧 🌸', 'emoji' => '🌸'];
        }

        if ($customer->streak_days >= 3) {
            $messages[] = ['message' => "連續 {$customer->streak_days} 天了！好認真呀", 'emoji' => '🔥'];
        }

        if ($customer->total_orders >= 1) {
            $messages[] = ['message' => '朵朵偷偷跟妳說：優惠正在冒芽中 🌱', 'emoji' => '🌱'];
        }

        $messages[] = ['message' => '嗨～朵朵今天很有元氣！', 'emoji' => '😊'];
        $messages[] = ['message' => '仙女氣色真好～', 'emoji' => '✨'];
        $messages[] = ['message' => '今天也要美美的喔', 'emoji' => '💖'];

        return $messages[array_rand($messages)] ?? null;
    }
}
