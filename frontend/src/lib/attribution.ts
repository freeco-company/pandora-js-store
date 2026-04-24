/**
 * Order-attribution helper. Captures landing-page UTM / click-id once and
 * persists it until checkout, so we can tell *which campaign* drove a sale
 * (not just which campaign drove a pageview).
 *
 * Flow:
 *   1. Analytics.tsx already detects utm/click on every page; we mirror
 *      that here and freeze the *first-touch* values in localStorage.
 *   2. Checkout reads the stored values via readAttribution() and sends
 *      them with the order POST.
 *   3. Backend saves on the order row for /admin/orders display + stats.
 *
 * First-touch vs last-touch:
 *   We use first-touch — if a user lands via IG, bounces to GSearch, then
 *   converts, the credit still goes to IG (who *introduced* them). Matches
 *   how small-business owners think about "which post sold this".
 *
 * Expiry: 30 days. Longer than typical analytics cookies because of DTC
 * buying cycles (add to wishlist → think for 2 weeks → come back → buy).
 */

const STORAGE_KEY = 'fp_attribution_v1';
const EXPIRY_DAYS = 30;
const PAID_MEDIUMS = new Set(['cpc', 'paid', 'ads', 'ppc']);

export interface Attribution {
  referer_source: string | null;
  utm_source: string | null;
  utm_medium: string | null;
  utm_campaign: string | null;
  landing_path: string | null;
  captured_at: number; // epoch ms
}

/**
 * Classify a URL's params into a normalized attribution source label,
 * mirroring VisitController::resolveSource on the backend (keep in sync
 * if you change one).
 */
function classifyClick(params: URLSearchParams): string | null {
  const utmMedium = (params.get('utm_medium') || '').toLowerCase();
  const isPaidMedium = PAID_MEDIUMS.has(utmMedium);

  if (params.get('gclid') || params.get('gbraid') || params.get('wbraid') || params.get('gad_source')) {
    return 'google_ads';
  }
  if (params.get('fbclid') && isPaidMedium) return 'facebook_ads';
  if (params.get('msclkid')) return 'bing_ads';
  if (params.get('ttclid')) return 'tiktok_ads';
  if (params.get('li_fat_id')) return 'linkedin_ads';

  const utmSource = (params.get('utm_source') || '').toLowerCase();
  if (utmSource) {
    if (utmSource.includes('google')) return isPaidMedium ? 'google_ads' : 'google';
    if (utmSource.includes('facebook') || utmSource === 'fb') return isPaidMedium ? 'facebook_ads' : 'facebook';
    if (utmSource.includes('instagram') || utmSource === 'ig') return isPaidMedium ? 'facebook_ads' : 'instagram';
    if (utmSource.includes('line')) return isPaidMedium ? 'other_ads' : 'line';
    if (utmSource.includes('email') || utmSource.includes('mail')) return 'email';
    return isPaidMedium ? 'other_ads' : 'other';
  }
  return null;
}

/**
 * Call once on landing (wired via Analytics.tsx). No-op if we already
 * have a fresh attribution stored (first-touch wins).
 */
export function captureAttribution(landingPath: string): void {
  if (typeof window === 'undefined') return;
  try {
    const existing = readAttribution();
    if (existing) return; // first-touch: don't overwrite

    const params = new URLSearchParams(window.location.search);
    const source = classifyClick(params);
    const utmSource = params.get('utm_source');
    const utmMedium = params.get('utm_medium');
    const utmCampaign = params.get('utm_campaign');

    // Only persist if we have *some* signal. Pure direct visits don't need
    // a row — backend will see null and show "—".
    if (!source && !utmSource && !utmMedium && !utmCampaign) return;

    const record: Attribution = {
      referer_source: source,
      utm_source: utmSource,
      utm_medium: utmMedium,
      utm_campaign: utmCampaign,
      landing_path: landingPath,
      captured_at: Date.now(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(record));
  } catch {
    // localStorage may be unavailable (private mode, quota) — silently no-op.
  }
}

export function readAttribution(): Attribution | null {
  if (typeof window === 'undefined') return null;
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as Attribution;
    const ageDays = (Date.now() - parsed.captured_at) / (1000 * 60 * 60 * 24);
    if (ageDays > EXPIRY_DAYS) {
      localStorage.removeItem(STORAGE_KEY);
      return null;
    }
    return parsed;
  } catch {
    return null;
  }
}

/** Order POST payload slice — spread into the body on checkout. */
export function attributionForOrderPayload(): Partial<Omit<Attribution, 'captured_at'>> {
  const a = readAttribution();
  if (!a) return {};
  return {
    referer_source: a.referer_source,
    utm_source: a.utm_source,
    utm_medium: a.utm_medium,
    utm_campaign: a.utm_campaign,
    landing_path: a.landing_path,
  };
}
