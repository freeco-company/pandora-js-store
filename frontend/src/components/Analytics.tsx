'use client';

import { useEffect } from 'react';
import Script from 'next/script';
import { usePathname } from 'next/navigation';

const GA_ID = process.env.NEXT_PUBLIC_GA_ID;
const META_PIXEL_ID = process.env.NEXT_PUBLIC_META_PIXEL_ID;

declare global {
  interface Window {
    gtag: (...args: unknown[]) => void;
    dataLayer: unknown[];
    fbq: (...args: unknown[]) => void;
    _fbq: (...args: unknown[]) => void;
  }
}

export default function Analytics() {
  const pathname = usePathname();

  // Track page views on route change
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
      {/* Google Analytics 4 */}
      {GA_ID && (
        <>
          <Script
            src={`https://www.googletagmanager.com/gtag/js?id=${GA_ID}`}
            strategy="afterInteractive"
          />
          <Script id="ga4-init" strategy="afterInteractive">
            {`
              window.dataLayer = window.dataLayer || [];
              function gtag(){dataLayer.push(arguments);}
              gtag('js', new Date());
              gtag('config', '${GA_ID}');
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
