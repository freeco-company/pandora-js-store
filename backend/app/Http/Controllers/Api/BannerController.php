<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $banners = Banner::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get([
                'id', 'title',
                'image', 'image_width', 'image_height',
                'mobile_image', 'mobile_image_width', 'mobile_image_height',
                'link',
            ]);

        return response()->json($banners);
    }
}
