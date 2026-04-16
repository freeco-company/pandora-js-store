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
            ->get(['id', 'title', 'image', 'mobile_image', 'link']);

        return response()->json($banners);
    }
}
