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
     * Redirect to Google OAuth — direct 302, no JSON roundtrip.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    /**
     * Handle Google OAuth callback — find or create Customer, redirect to frontend with token.
     */
    public function handleGoogleCallback(Request $request)
    {
        $frontendUrl = config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw');

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
                // password is NOT NULL in the DB. Google OAuth users don't
                // have a password but the column must have a value. Set a
                // random hash so the row passes strict-mode insertion.
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
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
     * Return the LINE OAuth redirect URL.
     *
     * LINE OAuth v2.1 requires a `state` parameter (unlike Google which
     * allows truly stateless flows). We generate a random state, sign it
     * with APP_KEY via HMAC so the callback can verify it without session
     * storage, and pass it through the OAuth redirect.
     */
    public function redirectToLine()
    {
        $state = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $state, config('app.key'));
        $statePayload = $state . '.' . $signature;

        return Socialite::driver('line')
            ->stateless()
            ->with(['state' => $statePayload])
            ->redirect();
    }

    /**
     * Handle LINE OAuth callback — find or create Customer, redirect to frontend with token.
     */
    public function handleLineCallback(Request $request)
    {
        $frontendUrl = config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw');

        try {
            // Verify the HMAC-signed state to prevent CSRF
            $stateParam = $request->query('state', '');
            if (!str_contains($stateParam, '.')) {
                throw new \RuntimeException('Invalid LINE OAuth state');
            }
            [$state, $signature] = explode('.', $stateParam, 2);
            $expected = hash_hmac('sha256', $state, config('app.key'));
            if (!hash_equals($expected, $signature)) {
                throw new \RuntimeException('LINE OAuth state signature mismatch');
            }

            $lineUser = Socialite::driver('line')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->to($frontendUrl . '/auth/line/callback?error=auth_failed');
        }

        $lineId = $lineUser->getId();
        $name = $lineUser->getName() ?: 'LINE 會員';
        $email = $lineUser->getEmail(); // May be null — LINE email is optional

        // Try matching by line_id first
        $customer = Customer::where('line_id', $lineId)->first();

        if (!$customer && $email) {
            // Try matching by email (link LINE to existing account)
            $customer = Customer::where('email', $email)->first();
            if ($customer) {
                $customer->update(['line_id' => $lineId, 'name' => $name]);
            }
        }

        if (!$customer) {
            // Create new customer
            $customer = Customer::create([
                'line_id' => $lineId,
                'name' => $name,
                'email' => $email ?? $lineId . '@line.user',
                'membership_level' => 'regular',
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        $token = $customer->createToken('line-auth')->plainTextToken;

        return redirect()->to($frontendUrl . '/auth/line/callback?token=' . urlencode($token));
    }

    /**
     * Return the currently authenticated customer.
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $customer->toArray();
        $data['auth_provider'] = $customer->line_id ? 'line' : ($customer->google_id ? 'google' : 'email');

        return response()->json($data);
    }
}
