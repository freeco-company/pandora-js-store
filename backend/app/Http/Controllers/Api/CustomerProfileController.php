<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logged-in customer profile + address-book CRUD.
 * All endpoints require Sanctum auth via /api/customer/* prefix.
 */
class CustomerProfileController extends Controller
{
    /* ------------ Profile ------------ */

    public function show(Request $request): JsonResponse
    {
        $c = $request->user();

        // Ensure referral code exists for legacy accounts
        if (! $c->referral_code) {
            $c->referral_code = \App\Models\Customer::generateReferralCode();
            $c->save();
        }

        $refCount = \App\Models\Customer::where('referred_by_customer_id', $c->id)->count();
        $refSuccessCount = \App\Models\Customer::where('referred_by_customer_id', $c->id)
            ->where('referral_reward_granted', true)
            ->count();

        // Determine login provider for frontend display
        $authProvider = $c->line_id ? 'line' : ($c->google_id ? 'google' : 'email');

        return response()->json([
            'id'         => $c->id,
            'name'       => $c->name,
            'email'      => $c->email,
            'phone'      => $c->phone,
            'is_vip'     => (bool) $c->is_vip,
            'membership_level' => $c->membership_level,
            'auth_provider' => $authProvider,
            'total_orders'     => $c->total_orders,
            'total_spent'      => (int) $c->total_spent,
            'referral_code'    => $c->referral_code,
            'referrals_count'  => $refCount,
            'referrals_success' => $refSuccessCount,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $c = $request->user();
        $c->fill($data)->save();

        return $this->show($request);
    }

    /* ------------ Addresses ------------ */

    public function addressIndex(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->addresses()->orderByDesc('is_default')->orderByDesc('id')->get()
        );
    }

    public function addressStore(Request $request): JsonResponse
    {
        $data = $this->validateAddress($request);
        $customer = $request->user();

        $address = DB::transaction(function () use ($customer, $data) {
            if (! empty($data['is_default'])) {
                $customer->addresses()->update(['is_default' => false]);
            }
            // First address auto-default
            if ($customer->addresses()->count() === 0) {
                $data['is_default'] = true;
            }
            return $customer->addresses()->create($data);
        });

        return response()->json($address, 201);
    }

    public function addressUpdate(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize($request, $address);
        $data = $this->validateAddress($request);

        DB::transaction(function () use ($request, $address, $data) {
            if (! empty($data['is_default'])) {
                $request->user()->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }
            $address->fill($data)->save();
        });

        return response()->json($address->fresh());
    }

    public function addressDestroy(Request $request, CustomerAddress $address): JsonResponse
    {
        $this->authorize($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();

        // Promote another address to default if the deleted one was default
        if ($wasDefault) {
            $next = $request->user()->addresses()->orderByDesc('id')->first();
            if ($next) $next->update(['is_default' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /* ------------ Helpers ------------ */

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label'          => ['nullable', 'string', 'max:50'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'phone'          => ['required', 'string', 'max:30'],
            'postal_code'    => ['nullable', 'string', 'max:10'],
            'city'           => ['nullable', 'string', 'max:40'],
            'district'       => ['nullable', 'string', 'max:40'],
            'street'         => ['required', 'string', 'max:255'],
            'is_default'     => ['nullable', 'boolean'],
        ]);
    }

    private function authorize(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $request->user()->id, 403);
    }
}
