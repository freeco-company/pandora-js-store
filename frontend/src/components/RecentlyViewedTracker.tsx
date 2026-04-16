'use client';

import { useEffect } from 'react';
import { trackRecentlyViewed } from '@/hooks/useRecentlyViewed';

/**
 * Fire-and-forget: records the current product view in localStorage.
 * Mounted once on the product detail page.
 */
export default function RecentlyViewedTracker({
  slug,
  name,
  image,
  price,
}: {
  slug: string;
  name: string;
  image: string | null;
  price: number;
}) {
  useEffect(() => {
    trackRecentlyViewed({ slug, name, image, price });
  }, [slug, name, image, price]);
  return null;
}
