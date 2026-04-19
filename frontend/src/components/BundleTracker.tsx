'use client';

import { useEffect } from 'react';
import { trackBundleView, trackBundleListView } from './Analytics';

type Bundle = {
  id: number;
  name: string;
  bundle_price: number;
  campaign?: { name: string } | null;
};

/**
 * Fires GA4 view_item once when a bundle detail page mounts.
 * Server-rendered pages mount this as a sibling so the page itself stays SSR.
 */
export function BundleViewTracker({ bundle }: { bundle: Bundle }) {
  useEffect(() => {
    trackBundleView(bundle);
    // bundle.id is the only stable identity here — re-fire only if user
    // navigates to a different bundle in the same session.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bundle.id]);
  return null;
}

/**
 * Fires GA4 view_item_list when a bundle grid mounts (campaign page, etc).
 * `listName` becomes item_list_name in reports.
 */
export function BundleListTracker({ bundles, listName }: { bundles: Bundle[]; listName: string }) {
  useEffect(() => {
    if (bundles.length === 0) return;
    trackBundleListView(bundles, listName);
    // Re-fire only when the list identity changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bundles.map((b) => b.id).join(','), listName]);
  return null;
}
