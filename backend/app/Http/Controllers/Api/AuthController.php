<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Return the Google OAuth redirect URL.
     */
    public function redirectToGoogle(): JsonResponse
    {
        $url = Socialite::driver('google')->stateless()->redirect()->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    /**
     * Handle Google OAuth callback — find or create Customer, redirect to frontend with token.
     */
    public function handleGoogleCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->to($frontendUrl . '/auth/google/callback?error=auth_failed');
        }

        $customer = Customer::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'membership_level' => 'regular',
            ]
        );

        // If an existing customer with the same email but no google_id, link them
        if (!$customer->wasRecentlyCreated) {
            // Already matched by google_id — good
        } else {
            // Check if a customer with this email already exists (from guest checkout)
            $existing = Customer::where('email', $googleUser->getEmail())
                ->whereNull('google_id')
                ->first();

            if ($existing) {
                // Link Google to existing customer, delete the duplicate
                $existing->update([
                    'google_id' => $googleUser->getId(),
                    'name' => $googleUser->getName(),
                ]);
                $customer->forceDelete();
                $customer = $existing;
            }
        }

        $token = $customer->createToken('google-auth')->plainTextToken;

        return redirect()->to($frontendUrl . '/auth/google/callback?token=' . urlencode($token));
    }

    /**
     * Return the currently authenticated customer.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
