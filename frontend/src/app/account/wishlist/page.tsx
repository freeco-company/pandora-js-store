import type { Metadata } from 'next';
import WishlistClient from './WishlistClient';

/**
 * Wishlist route — server wrapper exists solely to attach noindex.
 * Personal collection pages must never appear in search results.
 */
export const metadata: Metadata = {
  title: '我的收藏',
  robots: { index: false, follow: false },
};

export default function WishlistPage() {
  return <WishlistClient />;
}
