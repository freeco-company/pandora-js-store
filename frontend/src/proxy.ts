import { NextRequest, NextResponse, type NextFetchEvent } from 'next/server';
import { detectAiTraffic } from './lib/ai-traffic';

/**
 * Edge proxy (Next.js 16 — renamed from middleware):
 *
 * 1. AI traffic counter — fire-and-forget POST to /api/track/ai-visit
 *    whenever an AI crawler UA or AI-origin referer is seen. Aggregated
 *    on the backend so storage stays bounded.
 * 2. Legacy product slug redirect — 308 /products/{legacy_slug} to the
 *    canonical slug the backend has on file.
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://pandora.js-store.com.tw/api';
const memo = new Map<string, string | null>();

async function postAiVisit(
  detection: { botType: string; source: string },
  path: string
): Promise<void> {
  try {
    await fetch(`${API_URL}/track/ai-visit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        bot_type: detection.botType,
        source: detection.source,
        path: path.slice(0, 255),
      }),
    });
  } catch {
    // swallow — analytics is best-effort
  }
}

async function redirectLegacySlug(req: NextRequest): Promise<NextResponse | null> {
  const { pathname } = req.nextUrl;
  const match = pathname.match(/^\/products\/([^\/]+)$/);
  if (!match) return null;

  const slug = decodeURIComponent(match[1]);
  const looksLegacy = slug.startsWith('jerosse') || /份-盒|錠-盒|盒-\d/.test(slug);
  if (!looksLegacy) return null;

  let canonical = memo.get(slug);
  if (canonical === undefined) {
    try {
      const res = await fetch(`${API_URL}/products/${encodeURIComponent(slug)}`, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });
      if (res.ok) {
        const data = (await res.json()) as { slug?: string };
        canonical = data?.slug && data.slug !== slug ? data.slug : null;
      } else {
        canonical = null;
      }
    } catch {
      canonical = null;
    }
    memo.set(slug, canonical);
  }

  if (canonical) {
    const url = req.nextUrl.clone();
    url.pathname = `/products/${canonical}`;
    return NextResponse.redirect(url, 308);
  }

  return null;
}

export async function proxy(req: NextRequest, event: NextFetchEvent) {
  const detection = detectAiTraffic(
    req.headers.get('user-agent'),
    req.headers.get('referer')
  );
  if (detection) {
    // waitUntil keeps the promise alive past the proxy return, so we don't
    // block the response on analytics.
    event.waitUntil(postAiVisit(detection, req.nextUrl.pathname));
  }

  const redirect = await redirectLegacySlug(req);
  return redirect ?? NextResponse.next();
}

export const config = {
  matcher: [
    // Match everything except static assets + API proxied through Next.
    // Keep in sync with the asset extensions Next serves from public/.
    '/((?!_next/static|_next/image|favicon\\.ico|robots\\.txt|sitemap.*\\.xml|apple-touch-icon\\.png|icon-\\d+\\.png|manifest\\.json|images/|.*\\.(?:svg|png|jpg|jpeg|gif|webp|ico|css|js|woff|woff2|ttf|map)).*)',
  ],
};
