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

/**
 * WP /product/{wp_post_name} → Next /products/{new_slug}
 *
 * Key = 舊 WP post_name（decoded），Value = 新 products.slug。
 * 來源：awoo_wp.wp_posts JOIN backend products.wp_id。
 * Google 會丟 percent-encoded Chinese path，這邊統一 decode 後比對。
 */
const WP_PRODUCT_SLUG_MAP: Record<string, string> = {
  'jerosse-婕樂纖-雪花紫纖飲-14份-盒': '雪花紫纖飲-蔓越莓風味漂浮微泡飲-微氣泡莓果飲-喝水神器',
  '【郭雪芙代言推薦】-我的秘密-纖飄錠-60錠-盒': '纖飄錠-郭雪芙代言健康食品認證',
  'jerosse-婕樂纖-纖纖輕鬆飲x-14包-盒': '纖纖飲X-纖纖輕鬆飲X',
  'jerosse-婕樂纖-爆纖錠-120顆-瓶': '爆纖錠-小粉',
  '婕樂纖輕卡肽纖飲-官方授權正品-10包-盒': '輕卡肽纖飲-肽可可',
  '婕樂纖輕卡肽纖飲-厚焙奶茶-官方授權正品-10包-盒': '厚焙奶茶-肽纖飲奶茶口味-仙女奶茶-肽奶茶',
  'jerosse-婕樂纖-植萃纖酵宿-60錠-盒-官方授權': '植萃纖酵宿',
  'jerosse-hyaluronic-acid-tablets': '水光錠日本Hyabest專利玻尿酸-口服保養推薦',
  'jerosse-hydration-mask-bandage': '水光繃帶面膜繃帶普拉斯頂級修護-補水保濕',
  'jerosse-cleansing-gel': '婕肌零-J70婕肌零洗卸凝膠-洗卸保養三合一',
  'jerosse-婕樂纖-雪聚露-雙效導入精華-官方授權正品': '雪聚露-雙效導入精華',
  '婕樂纖-急救小白瓶-全效賦活絲絨身體精華油-100ml-瓶': '急救小白瓶-全效賦活絲絨身體精華油',
  'jerosse-婕樂纖-法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露': '法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露',
  'jerosse-婕樂纖-法樂蓬養髮原液-法樂蓬強健活化養髮原液': '法樂蓬養髮原液-法樂蓬強健活化養髮原液',
  'jerosse-probiotics-official': '高機能益生菌',
  'jerosse-婕樂纖-金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多': '金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多風味',
  'jerosse-婕樂纖-積雪草護手霜50ml-條-官方授權正品': '積雪草護手霜',
  'jerosse-婕樂纖-固樂纖dkkflex-60錠-盒-官方授權正品': '固樂纖DKKflex',
  'jerosse-婕樂纖-療肺草正冠茶20包-盒-官方授權正品': '療肺草正冠茶',
  'jerosse-婕樂纖-9國英雄turbo極速錠-20顆-包-官方授權正品': '9國英雄TURBO極速錠',
  'jerosse-shampoo-sale': '法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露',
  'jerosse-velvet-body-essence-oil': '急救小白瓶-全效賦活絲絨身體精華油',
  'jerosse-probiotics-buy3get1': '高機能益生菌',
  'jerosse-葉黃素晶亮凍優惠': '金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多風味',
};

const WP_BUNDLE_SLUGS = new Set([
  'jerosse-cny-2026-lucky-bag-bundle',
  'jerosse-cny-burn-firming-vip-plan',
]);

function redirectWpProduct(req: NextRequest): NextResponse | null {
  const { pathname } = req.nextUrl;
  if (!pathname.startsWith('/product/')) return null;

  const rawSlug = pathname.slice('/product/'.length).replace(/\/+$/, '');
  const url = req.nextUrl.clone();
  url.search = ''; // 丟掉 ?add-to-cart= 等 WooCommerce 參數

  if (!rawSlug) {
    url.pathname = '/products';
    return NextResponse.redirect(url, 301);
  }

  let decoded: string;
  try {
    decoded = decodeURIComponent(rawSlug);
  } catch {
    decoded = rawSlug;
  }

  if (decoded in WP_PRODUCT_SLUG_MAP) {
    url.pathname = `/products/${WP_PRODUCT_SLUG_MAP[decoded]}`;
    return NextResponse.redirect(url, 301);
  }
  if (WP_BUNDLE_SLUGS.has(decoded)) {
    // /bundles 沒有 index 路由，導 /products 保險
    url.pathname = '/products';
    return NextResponse.redirect(url, 301);
  }
  url.pathname = '/products';
  return NextResponse.redirect(url, 301);
}

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
    return NextResponse.redirect(url, 301);
  }

  return null;
}

// WooCommerce category slug → new category slug. Empty string = redirect to
// /products index (no direct mapping). Extend as new category landing pages
// are built.
//
// Note: next.config.ts `redirects()` handles the English-slug product-category
// URLs at the framework level (runs before middleware). This map survives as a
// fallback for requests that somehow skip the config redirects (edge cases).
const WP_CATEGORY_MAP: Record<string, string> = {
  health: '',
  beauty: '',
  supplements: '',
  'body-beauty-series': 'body-beauty-series',
};

function redirectWpTaxonomy(req: NextRequest): NextResponse | null {
  const { pathname } = req.nextUrl;
  const url = req.nextUrl.clone();
  url.search = ''; // strip WP query params (?orderby=, ?add-to-cart=, etc.)

  // WP shop index — /shop or /shop/page/N
  if (pathname === '/shop' || pathname.startsWith('/shop/')) {
    url.pathname = '/products';
    return NextResponse.redirect(url, 301);
  }

  // WP product category — /product-category/<slug> or /product-category/<slug>/page/N
  const cat = pathname.match(/^\/product-category\/([^\/]+)/);
  if (cat) {
    const slug = decodeURIComponent(cat[1]);
    const mapped = WP_CATEGORY_MAP[slug];
    url.pathname = mapped ? `/products/category/${mapped}` : '/products';
    return NextResponse.redirect(url, 301);
  }

  // WP product tag — /product-tag/<slug>
  if (pathname.startsWith('/product-tag/')) {
    url.pathname = '/products';
    return NextResponse.redirect(url, 301);
  }

  // WooCommerce account
  if (pathname === '/my-account' || pathname.startsWith('/my-account/')) {
    url.pathname = '/account';
    return NextResponse.redirect(url, 301);
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

  // WP query-param 舊連結：?post_type=product / ?page_id=xxx。
  // 放 proxy 不放 next.config 的 redirects()，是因為後者會把 request query 帶
  // 到 destination，造成 `/?page_id=3 → /?page_id=3` 無限迴圈。
  if (req.nextUrl.pathname === '/') {
    const q = req.nextUrl.searchParams;
    if (q.get('post_type') === 'product' || q.has('page_id')) {
      const url = req.nextUrl.clone();
      url.search = '';
      url.pathname = q.get('post_type') === 'product' ? '/products' : '/';
      return NextResponse.redirect(url, 301);
    }
  }

  const taxonomyRedirect = redirectWpTaxonomy(req);
  if (taxonomyRedirect) return taxonomyRedirect;

  const wpRedirect = redirectWpProduct(req);
  if (wpRedirect) return wpRedirect;

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
