<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;

/**
 * Human visit logger. Called fire-and-forget from Next.js proxy on every
 * page request that's NOT an AI bot. Stores raw event rows for later
 * admin-side analysis (UV by day, source breakdown, device mix, etc.).
 *
 * The hard parts — UA parsing and source normalization — happen here on
 * the backend, not in the proxy, so the edge stays thin.
 */
class VisitController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id'    => 'nullable|string|max:64',
            'path'          => 'nullable|string|max:512',
            'landing_path'  => 'nullable|string|max:512',
            'referer_url'   => 'nullable|string|max:2048',
            'user_agent'    => 'nullable|string|max:1000',
            'utm_source'    => 'nullable|string|max:64',
            'utm_medium'    => 'nullable|string|max:64',
            'utm_campaign'  => 'nullable|string|max:128',
            'country'       => 'nullable|string|max:2',
            // click_id + click_source come from ad-platform auto-tagging
            // (gclid / fbclid / msclkid / etc.). Definitive paid signal.
            'click_id'      => 'nullable|string|max:255',
            'click_source'  => 'nullable|string|max:32',
            // customer_id validated below by checking auth, not client input,
            // so we don't trust the frontend to claim an identity.
        ]);

        $ip = $this->clientIp($request);
        $ua = $data['user_agent'] ?? $request->userAgent() ?? '';
        $agent = new Agent();
        $agent->setUserAgent($ua);

        // Visitor id = hash(ip prefix + ua + YYYY-MM-DD). Truncating IPv4 to
        // /24 (and IPv6 to /64) makes the id stable across DHCP churn within
        // the same network, which lines up with how humans actually use
        // phones/laptops. Date component makes "today's unique visitors" a
        // simple GROUP BY.
        $visitorId = hash('sha256', $this->ipPrefix($ip) . '|' . $ua . '|' . now()->toDateString());

        Visit::create([
            'visitor_id'      => $visitorId,
            'session_id'      => $data['session_id'] ?? null,
            'ip'              => $ip,
            'country'         => $data['country'] ?? $request->header('CF-IPCountry'),
            'region'          => null,
            'user_agent'      => $ua ?: null,
            'device_type'     => $this->deviceType($agent),
            'os'              => $agent->platform() ?: null,
            'os_version'      => $agent->version($agent->platform() ?: '') ?: null,
            'browser'         => $agent->browser() ?: null,
            'browser_version' => $agent->version($agent->browser() ?: '') ?: null,
            // Bot UA wins over every other source signal. Without this, crawlers
            // that execute JS (AdsBot-Google-Mobile, headless Chrome, Puppeteer
            // etc.) leak into the "direct" bucket and inflate organic UV counts.
            // Keep the row so we can audit who's crawling us, but label it so
            // widgets can exclude it from organic traffic math.
            'referer_source'  => $this->isBot($ua) ? 'bot' : $this->normalizeSource(
                $data['referer_url'] ?? null,
                $data['utm_source'] ?? null,
                $data['utm_medium'] ?? null,
                $data['click_source'] ?? null,
            ),
            'referer_url'     => $data['referer_url'] ?? null,
            'utm_source'      => $data['utm_source'] ?? null,
            'utm_medium'      => $data['utm_medium'] ?? null,
            'utm_campaign'    => $data['utm_campaign'] ?? null,
            'landing_path'    => $data['landing_path'] ?? null,
            'path'            => $data['path'] ?? null,
            'customer_id'     => $request->user()?->id,
            'visited_at'      => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    private function clientIp(Request $request): ?string
    {
        // Cloudflare passes the real client IP here; fall back to standard
        // headers if we're bypassed (e.g. direct origin hit during debug).
        return $request->header('CF-Connecting-IP')
            ?? $request->header('X-Forwarded-For')
            ?? $request->ip();
    }

    private function ipPrefix(?string $ip): string
    {
        if (! $ip) return 'unknown';
        // IPv6 first because it contains colons; IPv4 detection uses dots.
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)); // /64-ish
        }
        $parts = explode('.', $ip);
        return count($parts) === 4 ? "{$parts[0]}.{$parts[1]}.{$parts[2]}" : $ip;
    }

    /**
     * UA-based bot detection. Catches well-known crawlers regardless of whether
     * the edge proxy's AI-traffic detector recognised them. Anything matching
     * here is bucketed as `referer_source = 'bot'` so it doesn't inflate the
     * admin dashboard's organic / direct counts.
     *
     * Intentionally broader than ai-traffic.ts (which only catches *AI* bots):
     * this also catches AdsBot, Googlebot, Bingbot, SEO crawlers, uptime
     * probes, and generic "bot/crawler/spider" UAs.
     */
    private function isBot(string $ua): bool
    {
        if ($ua === '') return false;
        // Broad but intentional. Ordering doesn't matter — this is a pure
        // match. AdsBot-Google-* is explicit because it shows up as a
        // "mobile Chrome" UA with "AdsBot-Google-Mobile" appended, which
        // would otherwise slip past a naive "bot" substring check on some
        // platforms that lowercase-normalise UA strings differently.
        return (bool) preg_match(
            '~'
            . 'AdsBot-Google|Googlebot|Bingbot|DuckDuckBot|Baiduspider|YandexBot|Sogou|Yeti|'
            . 'facebookexternalhit|facebookcatalog|LinkedInBot|Twitterbot|Slackbot|TelegramBot|Discordbot|'
            . 'WhatsApp|Line/|ia_archiver|archive\.org_bot|PetalBot|Seekport|AhrefsBot|SemrushBot|MJ12bot|'
            . 'DotBot|BLEXBot|DataForSeoBot|serpstatbot|ZoominfoBot|MegaIndex|'
            . 'HeadlessChrome|PhantomJS|puppeteer|Playwright|Selenium|Lighthouse|'
            . 'curl/|wget/|python-requests|Go-http-client|node-fetch|axios/|okhttp/|Java/|'
            . 'bot|crawler|crawling|spider|scraper'
            . '~i',
            $ua,
        );
    }

    private function deviceType(Agent $agent): string
    {
        if ($agent->isTablet()) return 'tablet';
        if ($agent->isMobile()) return 'mobile';
        return 'desktop';
    }

    /**
     * Bucket a visit into a normalized source label. Priority order:
     *   1. click_source (ad platform auto-tagging: gclid etc.) — definitive
     *      paid signal. Wins even over utm_* and referer.
     *   2. utm_source (explicit campaign tagging)
     *   3. utm_medium hints (cpc/paid/ads → treat as paid for the matching
     *      host inferred from referer)
     *   4. referer host (organic classification)
     *   5. Default: direct
     *
     * Keep bucket names aligned with Filament filter pill options.
     */
    private function normalizeSource(
        ?string $refererUrl,
        ?string $utmSource,
        ?string $utmMedium = null,
        ?string $clickSource = null,
    ): string {
        // 1. Click ID beats everything — if Google/Meta/etc tagged the URL
        //    with their ad-click identifier, it's definitionally paid.
        if ($clickSource) {
            return match (strtolower($clickSource)) {
                'google_ads' => 'google_ads',
                'facebook_ads' => 'facebook_ads',
                'bing_ads' => 'bing_ads',
                'tiktok_ads' => 'tiktok_ads',
                'linkedin_ads' => 'linkedin_ads',
                default => 'other_ads',
            };
        }

        // 2. utm_medium = cpc/paid/ads + utm_source present → paid.
        $isPaidMedium = $utmMedium && in_array(strtolower($utmMedium), ['cpc', 'paid', 'ads', 'ppc'], true);

        // 3. utm_source
        if ($utmSource) {
            $s = strtolower($utmSource);
            if (str_contains($s, 'google')) return ($isPaidMedium || str_contains($s, 'ads')) ? 'google_ads' : 'google';
            if (str_contains($s, 'facebook') || $s === 'fb') return $isPaidMedium ? 'facebook_ads' : 'facebook';
            if (str_contains($s, 'instagram') || $s === 'ig') return $isPaidMedium ? 'facebook_ads' : 'instagram';
            if (str_contains($s, 'line')) return $isPaidMedium ? 'other_ads' : 'line';
            if (str_contains($s, 'email') || str_contains($s, 'mail')) return 'email';
            if (str_contains($s, 'bing')) return $isPaidMedium ? 'bing_ads' : 'bing';
            return $isPaidMedium ? 'other_ads' : 'other';
        }

        // 4. Fall back to referer host
        if (! $refererUrl) return 'direct';

        $host = strtolower(parse_url($refererUrl, PHP_URL_HOST) ?? '');
        if ($host === '') return 'direct';

        if (str_contains($host, 'google.')) return $isPaidMedium ? 'google_ads' : 'google';
        if (str_contains($host, 'bing.com') || str_contains($host, 'duckduckgo')) return $isPaidMedium ? 'bing_ads' : 'bing';
        if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com') || str_contains($host, 'l.facebook')) return $isPaidMedium ? 'facebook_ads' : 'facebook';
        if (str_contains($host, 'instagram.com')) return $isPaidMedium ? 'facebook_ads' : 'instagram';
        if (str_contains($host, 'line.me') || str_contains($host, 't.co/line')) return 'line';
        if (str_contains($host, 'yahoo.')) return 'yahoo';
        if (str_contains($host, 'mail.')) return 'email';

        // Self-referral (same domain) is a navigation within the site, not
        // a new acquisition — bucket as direct so referer list stays useful.
        $selfHosts = ['pandora.js-store.com.tw', 'www.pandora.js-store.com.tw'];
        if (in_array($host, $selfHosts, true)) return 'direct';

        return 'other';
    }
}
