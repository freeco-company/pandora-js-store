import { API_URL } from '@/lib/api';
import { SITE_URL } from '@/lib/site';
import { NextRequest } from 'next/server';

/**
 * Marketing short-link redirect: /p/{code} → long URL with utm_*.
 *
 * Backend (Laravel) is the source of truth for click_count; this handler is
 * a thin proxy. We swallow API failures and fall back to the homepage so a
 * stale IG / LINE post never serves a 404 to a customer.
 *
 * 302 (not 301) on purpose — short-link target_url may change in /admin
 * (re-point a campaign mid-flight), and we don't want browsers caching the
 * old destination forever.
 */
export const dynamic = 'force-dynamic';

type Params = { params: Promise<{ code: string }> };

export async function GET(_req: NextRequest, { params }: Params) {
  const { code } = await params;

  // Validate shape before bothering the backend — same regex as Laravel.
  if (!/^[A-Za-z0-9-]{3,40}$/.test(code)) {
    return Response.redirect(SITE_URL, 302);
  }

  let target: string | null = null;
  try {
    const res = await fetch(`${API_URL}/short-links/${code}/resolve`, {
      cache: 'no-store',
    });
    if (res.ok) {
      const data = (await res.json()) as { url: string | null };
      target = data.url;
    }
  } catch {
    // network / backend down — fall through to fallback.
  }

  return Response.redirect(target || SITE_URL, 302);
}
