'use client';

import { useEffect } from 'react';
import Script from 'next/script';
import { usePathname } from 'next/navigation';

const GA_ID = process.env.NEXT_PUBLIC_GA_ID;
const GADS_ID = process.env.NEXT_PUBLIC_GOOGLE_ADS_ID;
const META_PIXEL_ID = process.env.NEXT_PUBLIC_META_PIXEL_ID;

declare global {
  interface Window {
    gtag: (...args: unknown[]) => void;
    dataLayer: unknown[];
    fbq: (...args: unknown[]) => void;
    _fbq: (...args: unknown[]) => void;
  }
}

/** Track add-to-cart event across all platforms */
export function trackAddToCart(productName: string, price: number, productId?: number) {
  if (typeof window === 'undefined') return;
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'add_to_cart', {
      currency: 'TWD',
      value: price,
      items: [{ item_id: productId, item_name: productName, price }],
    });
  }
  if (typeof window.fbq === 'function') {
    window.fbq('track', 'AddToCart', { content_name: productName, value: price, currency: 'TWD' });
  }
}

/** Track purchase completion */
export function trackPurchase(orderId: string, total: number, items: { name: string; price: number; qty: number }[]) {
  if (typeof window === 'undefined') return;
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'purchase', {
      transaction_id: orderId,
      value: total,
      currency: 'TWD',
      items: items.map((i) => ({ item_name: i.name, price: i.price, quantity: i.qty })),
    });
    // Google Ads conversion
    if (GADS_ID) {
      window.gtag('event', 'conversion', {
        send_to: `${GADS_ID}/purchase`,
        value: total,
        currency: 'TWD',
        transaction_id: orderId,
      });
    }
  }
  if (typeof window.fbq === 'function') {
    window.fbq('track', 'Purchase', { value: total, currency: 'TWD', content_type: 'product' });
  }
}

/** Track begin checkout */
export function trackBeginCheckout(total: number) {
  if (typeof window === 'undefined') return;
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'begin_checkout', { value: total, currency: 'TWD' });
  }
  if (typeof window.fbq === 'function') {
    window.fbq('track', 'InitiateCheckout', { value: total, currency: 'TWD' });
  }
}

export default function Analytics() {
  const pathname = usePathname();

  useEffect(() => {
    if (GA_ID && typeof window.gtag === 'function') {
      window.gtag('config', GA_ID, { page_path: pathname });
    }
    if (META_PIXEL_ID && typeof window.fbq === 'function') {
      window.fbq('track', 'PageView');
    }
  }, [pathname]);

  return (
    <>
      {/* Google Analytics 4 + Google Ads */}
      {(GA_ID || GADS_ID) && (
        <>
          <Script
            src={`https://www.googletagmanager.com/gtag/js?id=${GA_ID || GADS_ID}`}
            strategy="afterInteractive"
          />
          <Script id="ga-init" strategy="afterInteractive">
            {`
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              ${GA_ID ? `gtag('config', '${GA_ID}');` : ''}
              ${GADS_ID ? `gtag('config', '${GADS_ID}');` : ''}
            `}
          </Script>
        </>
      )}

      {/* Meta Pixel */}
      {META_PIXEL_ID && (
        <Script id="meta-pixel-init" strategy="afterInteractive">
          {`
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '${META_PIXEL_ID}');
            fbq('track', 'PageView');
          `}
        </Script>
      )}
    </>
  );
}
