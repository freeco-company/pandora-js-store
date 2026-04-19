/**
 * Wishlist storage helpers — handles the guest/auth split.
 *
 * - Guest: list lives in localStorage under WISHLIST_KEY as int[]
 * - Auth:  list lives server-side; the WishlistProvider mirrors it
 *          into local state (no localStorage write while authed)
 *
 * On login the WishlistProvider POSTs the localStorage list to /wishlist/sync
 * then clears localStorage so the two stores don't drift.
 */

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';
const WISHLIST_KEY = 'pandora-wishlist';

export function readGuestWishlist(): number[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = localStorage.getItem(WISHLIST_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed.filter((n) => typeof n === 'number') : [];
  } catch {
    return [];
  }
}

export function writeGuestWishlist(ids: number[]): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(WISHLIST_KEY, JSON.stringify(Array.from(new Set(ids))));
  } catch {
    // quota exceeded → ignore; in-memory state still authoritative
  }
}

export function clearGuestWishlist(): void {
  if (typeof window === 'undefined') return;
  try { localStorage.removeItem(WISHLIST_KEY); } catch {}
}

// --- Auth-mode API calls ---

export interface WishlistItem {
  id: number;
  product_id: number;
  created_at: string;
  product: {
    id: number;
    slug: string;
    name: string;
    image: string | null;
    price: number;
    combo_price: number | null;
    vip_price: number | null;
    stock_status: string | null;
    is_active: boolean;
  };
}

async function authFetch<T>(path: string, token: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
      ...(init?.headers || {}),
    },
  });
  if (!res.ok) throw new Error(`wishlist api ${res.status}`);
  return res.json();
}

export const fetchServerWishlist = (token: string) =>
  authFetch<{ items: WishlistItem[] }>('/wishlist', token);

export const addServerWishlist = (token: string, productId: number) =>
  authFetch<{ ok: boolean }>('/wishlist', token, {
    method: 'POST', body: JSON.stringify({ product_id: productId }),
  });

export const removeServerWishlist = (token: string, productId: number) =>
  authFetch<{ ok: boolean }>(`/wishlist/${productId}`, token, { method: 'DELETE' });

export const syncServerWishlist = (token: string, productIds: number[]) =>
  authFetch<{ ok: boolean; merged: number }>('/wishlist/sync', token, {
    method: 'POST', body: JSON.stringify({ product_ids: productIds }),
  });
