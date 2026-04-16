<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use Illuminate\Http\JsonResponse;

class PopupController extends Controller
{
    public function index(): JsonResponse
    {
        $popups = Popup::active()
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'image', 'link', 'content', 'display_frequency']);

        return response()->json($popups);
    }
}
