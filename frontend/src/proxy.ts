import { NextRequest, NextResponse } from 'next/server';

/**
 * Edge proxy (Next.js 16 — renamed from middleware): 308 permanent-redirect
 * legacy product slugs.
 *
 * When a user hits /products/{legacy_slug}, we fetch the canonical slug from
 * the backend (which remembers slug_legacy) and 308 them to the new URL.
 * Results are memo-cached per-slug in the worker for the life of the process.
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://pandora.js-store.com.tw/api';
const memo = new Map<string, string | null>();

export async function proxy(req: NextRequest) {
  const { pathname } = req.nextUrl;

  const match = pathname.match(/^\/products\/([^\/]+)$/);
  if (!match) return NextResponse.next();

  const slug = decodeURIComponent(match[1]);

  // Heuristic: only check URLs that look WP-legacy. New slugs never start with
  // "jerosse" or contain "份-盒" packaging tokens. Skip the API call otherwise
  // to keep normal product page views fast.
  const looksLegacy = slug.startsWith('jerosse') || /份-盒|錠-盒|盒-\d/.test(slug);
  if (!looksLegacy) return NextResponse.next();

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

  return NextResponse.next();
}

export const config = {
  matcher: ['/products/:slug*'],
};
