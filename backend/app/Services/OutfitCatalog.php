<?php

namespace App\Services;

class OutfitCatalog
{
    /**
     * Outfit catalog. Unlock types: 'orders', 'spend', 'streak', 'achievements'.
     * Slot: head, face, neck, crown.
     */
    public static function all(): array
    {
        return [
            'acorn_hat'   => ['name' => '橡實帽',   'slot' => 'head', 'emoji' => 'acorn-hat', 'unlock' => ['type' => 'orders', 'value' => 1]],
            'ribbon'      => ['name' => '蝴蝶結',   'slot' => 'head', 'emoji' => 'ribbon-bow', 'unlock' => ['type' => 'orders', 'value' => 3]],
            'beret'       => ['name' => '貝雷帽',   'slot' => 'head', 'emoji' => 'beret', 'unlock' => ['type' => 'orders', 'value' => 5]],
            'flower_crown'=> ['name' => '花冠',     'slot' => 'crown','emoji' => 'flower-crown', 'unlock' => ['type' => 'orders', 'value' => 10]],
            'star_halo'   => ['name' => '星光光環', 'slot' => 'crown','emoji' => 'star-halo', 'unlock' => ['type' => 'achievements', 'value' => 8]],

            'glasses'     => ['name' => '眼鏡',     'slot' => 'face', 'emoji' => 'glasses', 'unlock' => ['type' => 'streak', 'value' => 3]],
            'sunglasses'  => ['name' => '墨鏡',     'slot' => 'face', 'emoji' => 'sunglasses', 'unlock' => ['type' => 'streak', 'value' => 7]],
            'heart_eyes'  => ['name' => '愛心眼',   'slot' => 'face', 'emoji' => 'heart-eyes', 'unlock' => ['type' => 'spend', 'value' => 5000]],

            'scarf'       => ['name' => '圍巾',     'slot' => 'neck', 'emoji' => 'scarf', 'unlock' => ['type' => 'spend', 'value' => 1000]],
            'pearl'       => ['name' => '珍珠項鍊', 'slot' => 'neck', 'emoji' => 'pearl', 'unlock' => ['type' => 'spend', 'value' => 10000]],
        ];
    }

    public static function backdrops(): array
    {
        return [
            'meadow'   => ['name' => '草原',   'emoji' => 'sprout', 'unlock' => ['type' => 'orders', 'value' => 0]],
            'garden'   => ['name' => '花園',   'emoji' => 'cherry-blossom', 'unlock' => ['type' => 'orders', 'value' => 2]],
            'sakura'   => ['name' => '櫻花',   'emoji' => 'flower', 'unlock' => ['type' => 'orders', 'value' => 5]],
            'starry'   => ['name' => '星空',   'emoji' => 'star', 'unlock' => ['type' => 'streak', 'value' => 7]],
            'rainbow'  => ['name' => '彩虹',   'emoji' => 'rainbow', 'unlock' => ['type' => 'achievements', 'value' => 10]],
            'beach'    => ['name' => '海邊',   'emoji' => 'compass', 'unlock' => ['type' => 'spend', 'value' => 3000]],
        ];
    }

    public static function get(string $code): ?array
    {
        return self::all()[$code] ?? self::backdrops()[$code] ?? null;
    }
}
