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

/** First-party pageview ping for the dashboard 今日瀏覽人數 widget. */
function trackPageView(pathname: string) {
  if (typeof window === 'undefined') return;
  try {
    let sid = localStorage.getItem('pandora-session-id');
    if (!sid) {
      sid = crypto.randomUUID();
      localStorage.setItem('pandora-session-id', sid);
    }
    const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
    fetch(`${apiUrl}/track/view`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        path: pathname,
        session_id: sid,
        referer: document.referrer || null,
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
      {/* Google Tag Manager — loads GA4, Ads, Meta Pixel, LINE Tag, etc. */}
      <Script id="gtm-init" strategy="afterInteractive">
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
