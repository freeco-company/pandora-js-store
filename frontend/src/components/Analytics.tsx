'use client';

import { useEffect } from 'react';
import Script from 'next/script';
import { usePathname } from 'next/navigation';

const GTM_ID = process.env.NEXT_PUBLIC_GTM_ID;

declare global {
  interface Window {
    dataLayer: Record<string, unknown>[];
  }
}

/**
 * Push an ecommerce event to GTM's dataLayer.
 * All tag logic (GA4, Google Ads conversion, Meta Pixel, LINE Tag…)
 * lives inside GTM — no vendor-specific code here.
 */
export function pushEvent(event: string, data: Record<string, unknown> = {}) {
  if (typeof window === 'undefined') return;
  window.dataLayer = window.dataLayer || [];
  // Clear previous ecommerce object to avoid stale data bleeding across events
  window.dataLayer.push({ ecommerce: null });
  window.dataLayer.push({ event, ...data });
}

/**
 * Mirror key ecommerce events to the backend /api/track/cart-event endpoint
 * so the pipeline daily report can compute funnel rates (add-to-cart rate,
 * checkout initiation rate) without needing GA4 Reporting API credentials.
 *
 * GTM remains the source of truth for tag integrations — this is a parallel
 * write, fire-and-forget. Failures are swallowed so analytics errors never
 * bubble into the UI.
 */
type CartEventType = 'view_item' | 'add_to_cart' | 'remove_from_cart' | 'begin_checkout' | 'purchase';
function postCartEvent(params: {
  event_type: CartEventType;
  product_id?: number;
  bundle_id?: number;
  quantity?: number;
  value?: number;
}) {
  if (typeof window === 'undefined') return;
  const sid = localStorage.getItem('pandora-session-id');
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
  fetch(`${apiUrl}/track/cart-event`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    keepalive: true,
    body: JSON.stringify({ session_id: sid, ...params }),
  }).catch(() => { /* analytics is best-effort */ });
}

/**
 * GA4 ecommerce item — used by every cart/checkout/bundle event so reports
 * can break down revenue by item_category (product vs bundle) etc.
 */
export type GtmItem = {
  item_id: string;
  item_name: string;
  price: number;
  quantity: number;
  item_category?: string;
  item_brand?: string;
};

export function trackAddToCart(productName: string, price: number, productId?: number, quantity = 1) {
  pushEvent('add_to_cart', {
    ecommerce: {
      currency: 'TWD',
      value: price * quantity,
      items: [{
        item_id: String(productId),
        item_name: productName,
        price,
        quantity,
        item_category: 'product',
      }],
    },
  });
  postCartEvent({
    event_type: 'add_to_cart',
    product_id: productId,
    quantity,
    value: price * quantity,
  });
}

/**
 * Bundle add_to_cart — reuses the GA4 add_to_cart event but flags item_category=bundle
 * and uses item_id="bundle_<id>" so reports can attribute revenue to bundles.
 */
export function trackBundleAddToCart(bundle: {
  id: number;
  name: string;
  bundle_price: number;
  campaign?: { name: string } | null;
}, quantity = 1) {
  pushEvent('add_to_cart', {
    ecommerce: {
      currency: 'TWD',
      value: bundle.bundle_price * quantity,
      items: [{
        item_id: `bundle_${bundle.id}`,
        item_name: bundle.name,
        price: bundle.bundle_price,
        quantity,
        item_category: 'bundle',
        ...(bundle.campaign?.name ? { item_brand: bundle.campaign.name } : {}),
      }],
    },
  });
  postCartEvent({
    event_type: 'add_to_cart',
    bundle_id: bundle.id,
    quantity,
    value: bundle.bundle_price * quantity,
  });
}

/** Bundle detail page view — fires on /bundles/[slug]. */
export function trackBundleView(bundle: {
  id: number;
  name: string;
  bundle_price: number;
  campaign?: { name: string } | null;
}) {
  pushEvent('view_item', {
    ecommerce: {
      currency: 'TWD',
      value: bundle.bundle_price,
      items: [{
        item_id: `bundle_${bundle.id}`,
        item_name: bundle.name,
        price: bundle.bundle_price,
        quantity: 1,
        item_category: 'bundle',
        ...(bundle.campaign?.name ? { item_brand: bundle.campaign.name } : {}),
      }],
    },
  });
}

/** Bundle list view — fires on /campaigns/[slug] and any other bundle grid. */
export function trackBundleListView(
  bundles: Array<{ id: number; name: string; bundle_price: number }>,
  listName: string,
) {
  pushEvent('view_item_list', {
    ecommerce: {
      item_list_name: listName,
      items: bundles.map((b, i) => ({
        item_id: `bundle_${b.id}`,
        item_name: b.name,
        price: b.bundle_price,
        quantity: 1,
        item_category: 'bundle',
        index: i,
      })),
    },
  });
}

export function trackBeginCheckout(total: number, items: GtmItem[]) {
  pushEvent('begin_checkout', {
    ecommerce: {
      currency: 'TWD',
      value: total,
      items,
    },
  });
  postCartEvent({
    event_type: 'begin_checkout',
    value: total,
    quantity: items.reduce((sum, i) => sum + i.quantity, 0) || 1,
  });
}

export function trackPurchase(
  orderId: string,
  total: number,
  items: GtmItem[],
  paymentMethod?: string,
) {
  pushEvent('purchase', {
    ecommerce: {
      transaction_id: orderId,
      currency: 'TWD',
      value: total,
      payment_type: paymentMethod,
      items,
    },
  });
}

/**
 * First-party page-view ping for the admin "當日流量" list + dashboard widget.
 * Posts rich context (UA, referer, UTM, landing path) so the backend can parse
 * device/OS/source without a second round-trip. keepalive:true survives the
 * beforeunload race when users click out mid-request.
 */
function trackPageView(pathname: string) {
  if (typeof window === 'undefined') return;
  try {
    let sid = localStorage.getItem('pandora-session-id');
    if (!sid) {
      sid = crypto.randomUUID();
      localStorage.setItem('pandora-session-id', sid);
    }

    // First hit of this session gets landing_path; later hits in the same
    // session reuse it so we can answer "where did this visitor arrive?"
    let landing = sessionStorage.getItem('pandora-landing-path');
    if (!landing) {
      landing = pathname;
      sessionStorage.setItem('pandora-landing-path', landing);
    }

    // Freeze first-touch attribution in localStorage (30d) so the order
    // POST at checkout knows which campaign introduced this customer.
    // Pure pageview analytics stays in the separate visits flow below.
    import('@/lib/attribution').then(m => m.captureAttribution(landing!)).catch(() => {});

    const params = new URLSearchParams(window.location.search);

    // Paid-click IDs — most are definitive paid signals (gclid, msclkid,
    // ttclid, li_fat_id only auto-append on real ad clicks). fbclid is the
    // odd one out: Meta appends it to EVERY link click inside FB/IG/
    // Messenger apps, organic or paid. Requiring utm_medium=paid for fbclid
    // stops us classifying organic shares as "Meta Ads".
    //   gclid / gbraid / wbraid / gad_source → Google Ads
    //   fbclid + utm_medium=cpc/paid/ads     → Meta Ads (paid)
    //   fbclid alone                         → facebook organic (ignored here)
    //   msclkid                              → Microsoft (Bing) ads
    //   ttclid                               → TikTok ads
    //   li_fat_id                            → LinkedIn ads
    const utmMedium = (params.get('utm_medium') || '').toLowerCase();
    const isPaidMedium = ['cpc', 'paid', 'ads', 'ppc'].includes(utmMedium);
    const fbclidPaid = !!(params.get('fbclid') && isPaidMedium);

    const clickId =
      params.get('gclid') ||
      params.get('gbraid') ||
      params.get('wbraid') ||
      params.get('gad_source') ||
      (fbclidPaid ? params.get('fbclid') : null) ||
      params.get('msclkid') ||
      params.get('ttclid') ||
      params.get('li_fat_id') ||
      null;
    const clickIdSource = params.get('gclid') || params.get('gbraid') || params.get('wbraid') || params.get('gad_source')
      ? 'google_ads'
      : fbclidPaid ? 'facebook_ads'
      : params.get('msclkid') ? 'bing_ads'
      : params.get('ttclid') ? 'tiktok_ads'
      : params.get('li_fat_id') ? 'linkedin_ads'
      : null;

    // Persist the arrival attribution for the whole session so follow-up
    // page views stay attributed to the campaign that brought them in.
    // (Without this, everything after the landing page looks "direct".)
    if (clickIdSource) {
      sessionStorage.setItem('pandora-click-source', clickIdSource);
      if (clickId) sessionStorage.setItem('pandora-click-id', clickId);
    }
    const sessionClickSource = sessionStorage.getItem('pandora-click-source');
    const sessionClickId = sessionStorage.getItem('pandora-click-id');

    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
    fetch(`${apiUrl}/track/visit`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        session_id: sid,
        path: pathname,
        landing_path: landing,
        referer_url: document.referrer || null,
        user_agent: navigator.userAgent,
        utm_source: params.get('utm_source'),
        utm_medium: params.get('utm_medium'),
        utm_campaign: params.get('utm_campaign'),
        click_id: clickId || sessionClickId,
        click_source: clickIdSource || sessionClickSource,
      }),
      keepalive: true,
    }).catch(() => {});
  } catch {
    // ignore — tracking failure must never break the page
  }
}

export default function Analytics() {
  const pathname = usePathname();

  // Virtual pageview on client-side navigation
  useEffect(() => {
    pushEvent('page_view', { page_path: pathname });
    trackPageView(pathname);
  }, [pathname]);

  if (!GTM_ID) return null;

  return (
    <>
      {/* Google Tag Manager — lazyOnload so it doesn't block LCP/TBT.
          GTM injects ~265KB; loading after the window load event drops
          mobile Lighthouse perf score by ~15-20 points if loaded eagerly.
          Events fire to window.dataLayer regardless — GTM processes the
          queue on init, so add_to_cart / purchase / etc never get lost. */}
      <Script id="gtm-init" strategy="lazyOnload">
        {`
          (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
          new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
          j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
          'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
          })(window,document,'script','dataLayer','${GTM_ID}');
        `}
      </Script>
    </>
  );
}
