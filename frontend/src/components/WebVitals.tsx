'use client';

import { useEffect } from 'react';

/**
 * Reports Core Web Vitals (LCP, CLS, INP, TTFB) to the console
 * and GTM dataLayer. Loaded lazily — zero impact on initial bundle.
 */
export default function WebVitals() {
  useEffect(() => {
    import('web-vitals').then(({ onCLS, onLCP, onINP, onTTFB }) => {
      const report = (metric: { name: string; value: number; rating: string }) => {
        if (process.env.NODE_ENV === 'development') {
          console.log(`[Web Vitals] ${metric.name}: ${metric.value.toFixed(2)} (${metric.rating})`);
        }
        // Send to GTM dataLayer if available (picked up by GA4 automatically)
        const w = window as unknown as { dataLayer?: Record<string, unknown>[] };
        if (w.dataLayer) {
          w.dataLayer.push({
            event: 'web_vitals',
            metric_name: metric.name,
            metric_value: metric.value,
            metric_rating: metric.rating,
          });
        }
      };
      onCLS(report);
      onLCP(report);
      onINP(report);
      onTTFB(report);
    }).catch(() => {
      // web-vitals not installed — skip silently
    });
  }, []);

  return null;
}
