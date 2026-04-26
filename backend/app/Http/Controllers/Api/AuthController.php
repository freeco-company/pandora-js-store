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

        $googleId = $googleUser->getId();
        $name = $googleUser->getName();
        $email = $googleUser->getEmail();

        // Three cases, mirrored from LINE callback:
        //   1. Already have this google_id → just log them in.
        //   2. Have a customer with this email but no google_id → link Google to that account.
        //   3. Totally new → create.
        //
        // 用 Customer::findByIdentity 統一查詢，自動兼容 customer_identities 多 identity
        // （客戶曾經換過 email，舊 email 還能找回原帳號）+ fallback 查 customers 欄位。
        $customer = Customer::findByIdentity('google_id', $googleId);

        if (! $customer && $email) {
            $customer = Customer::findByIdentity('email', $email);
            if ($customer) {
                $updates = ['google_id' => $googleId];
                if (! $customer->name && $name) $updates['name'] = $name;
                $customer->update($updates);
            }
        }

        if (! $customer) {
            $customer = Customer::create([
                'google_id' => $googleId,
                'name' => $name,
                'email' => $email,
                'membership_level' => 'regular',
                // password NOT NULL in DB; Google users don't have one, so
                // seed a random hash they can't sign in with directly.
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
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

        // Same pattern as Google：先 line_id，再 email fallback；用 findByIdentity 統一查詢
        $customer = Customer::findByIdentity('line_id', $lineId);

        if (!$customer && $email) {
            $customer = Customer::findByIdentity('email', $email);
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
