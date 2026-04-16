<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    /**
     * Validate a coupon code and calculate the discount.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => '找不到此優惠碼。'], 404);
        }

        if (!$coupon->isValid()) {
            if (!$coupon->is_active) {
                return response()->json(['message' => '此優惠碼已停用。'], 422);
            }
            if ($coupon->expires_at && $coupon->expires_at->isPast()) {
                return response()->json(['message' => '此優惠碼已過期。'], 422);
            }
            if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
                return response()->json(['message' => '此優惠碼已達使用上限。'], 422);
            }
        }

        if ($coupon->min_amount && $request->subtotal < $coupon->min_amount) {
            return response()->json([
                'message' => "訂單金額需滿 NT\${$coupon->min_amount} 才能使用此優惠碼。",
            ], 422);
        }

        // Calculate discount
        $discount = match ($coupon->type) {
            'fixed' => min($coupon->value, $request->subtotal),
            'percentage' => round($request->subtotal * ($coupon->value / 100), 0),
            default => 0,
        };

        return response()->json([
            'coupon' => $coupon,
            'discount' => $discount,
        ]);
    }
}
