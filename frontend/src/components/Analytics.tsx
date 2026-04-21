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

    const params = new URLSearchParams(window.location.search);

    // Paid-click IDs — every major ad platform auto-tags landing URLs with
    // one of these when traffic comes from paid. Presence = definitive paid
    // signal, independent of whether utm_medium was also set.
    //   gclid / gbraid / wbraid / gad_source → Google Ads
    //   fbclid                               → Meta (FB/IG) ads
    //   msclkid                              → Microsoft (Bing) ads
    //   ttclid                               → TikTok ads
    //   li_fat_id                            → LinkedIn ads
    const clickId =
      params.get('gclid') ||
      params.get('gbraid') ||
      params.get('wbraid') ||
      params.get('gad_source') ||
      params.get('fbclid') ||
      params.get('msclkid') ||
      params.get('ttclid') ||
      params.get('li_fat_id') ||
      null;
    const clickIdSource = params.get('gclid') || params.get('gbraid') || params.get('wbraid') || params.get('gad_source')
      ? 'google_ads'
      : params.get('fbclid') ? 'facebook_ads'
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
