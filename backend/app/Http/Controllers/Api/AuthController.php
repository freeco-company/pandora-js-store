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
    public function redirectToLine(Request $request)
    {
        $payload = ['n' => bin2hex(random_bytes(8))];

        // Optional: piggy-back the COD-confirmation bind intent onto the same
        // OAuth dance. Frontend hits /api/auth/line?intent=bind-order&order=X&token=Y
        // when the customer taps "加 LINE 確認出貨" on order-complete. The
        // callback verifies the token and binds the line_user_id to the order.
        if ($request->query('intent') === 'bind-order') {
            $order = (string) $request->query('order', '');
            $token = (string) $request->query('token', '');
            if ($order !== '' && $token !== '') {
                $payload['i'] = 'bind-order';
                $payload['o'] = $order;
                $payload['t'] = $token;
            }
        }

        $statePayload = $this->encodeLineState($payload);

        return Socialite::driver('line')
            ->stateless()
            ->with(['state' => $statePayload])
            ->redirect();
    }

    private function encodeLineState(array $payload): string
    {
        $body = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $body, config('app.key'));
        return $body . '.' . $signature;
    }

    private function decodeLineState(string $stateParam): ?array
    {
        if (!str_contains($stateParam, '.')) return null;
        [$body, $signature] = explode('.', $stateParam, 2);
        $expected = hash_hmac('sha256', $body, config('app.key'));
        if (!hash_equals($expected, $signature)) return null;

        $padded = $body . str_repeat('=', (4 - strlen($body) % 4) % 4);
        $json = base64_decode(strtr($padded, '-_', '+/'), true);
        if ($json === false) return null;
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Handle LINE OAuth callback — find or create Customer, redirect to frontend with token.
     */
    public function handleLineCallback(Request $request)
    {
        $frontendUrl = config('services.ecpay.frontend_url', 'https://pandora.js-store.com.tw');

        $stateData = $this->decodeLineState((string) $request->query('state', ''));
        if ($stateData === null) {
            return redirect()->to($frontendUrl . '/auth/line/callback?error=auth_failed');
        }

        try {
            $lineUser = Socialite::driver('line')->stateless()->user();
        } catch (\Exception $e) {
            return redirect()->to($frontendUrl . '/auth/line/callback?error=auth_failed');
        }

        $lineId = $lineUser->getId();
        $name = $lineUser->getName() ?: 'LINE 會員';
        $email = $lineUser->getEmail(); // May be null — LINE email is optional

        // bind-order intent: 把 LINE userId 綁到 pending_confirmation 訂單上，
        // 並推 Flex 確認訊息。完成後導回 order-complete 顯示「已加入 LINE，等待您點按鈕確認」
        if (($stateData['i'] ?? null) === 'bind-order') {
            $orderNumber = (string) ($stateData['o'] ?? '');
            $token = (string) ($stateData['t'] ?? '');
            $bindResult = app(\App\Http\Controllers\Api\OrderConfirmationController::class)
                ->bindLineAndPush($orderNumber, $token, $lineId, $name, $email);

            $qs = http_build_query([
                'order' => $orderNumber,
                'bound' => $bindResult ? '1' : '0',
            ]);
            return redirect()->to($frontendUrl . '/order-complete?' . $qs);
        }

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
