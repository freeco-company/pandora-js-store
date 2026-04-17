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

export function trackAddToCart(productName: string, price: number, productId?: number, quantity = 1) {
  pushEvent('add_to_cart', {
    ecommerce: {
      currency: 'TWD',
      value: price * quantity,
      items: [{ item_id: String(productId), item_name: productName, price, quantity }],
    },
  });
}

export function trackBeginCheckout(total: number, items: { id: number; name: string; price: number; qty: number }[]) {
  pushEvent('begin_checkout', {
    ecommerce: {
      currency: 'TWD',
      value: total,
      items: items.map((i) => ({
        item_id: String(i.id),
        item_name: i.name,
        price: i.price,
        quantity: i.qty,
      })),
    },
  });
}

export function trackPurchase(
  orderId: string,
  total: number,
  items: { id: number; name: string; price: number; qty: number }[],
  paymentMethod?: string,
) {
  pushEvent('purchase', {
    ecommerce: {
      transaction_id: orderId,
      currency: 'TWD',
      value: total,
      payment_type: paymentMethod,
      items: items.map((i) => ({
        item_id: String(i.id),
        item_name: i.name,
        price: i.price,
        quantity: i.qty,
      })),
    },
  });
}

export default function Analytics() {
  const pathname = usePathname();

  // Virtual pageview on client-side navigation
  useEffect(() => {
    pushEvent('page_view', { page_path: pathname });
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
