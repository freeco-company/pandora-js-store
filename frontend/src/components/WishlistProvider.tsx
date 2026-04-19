'use client';

/**
 * Wishlist state — single source of truth, used by ♡ buttons across the
 * site and by the /account/wishlist page.
 *
 * Two storage modes (Provider transparently switches):
 *   - Guest: list lives in localStorage as int[]
 *   - Auth:  list lives server-side; mutations call API + update local
 *
 * Login transition: when token appears the provider POSTs the localStorage
 * list to /wishlist/sync, fetches the merged server list, and clears
 * localStorage so the two stores can't drift.
 *
 * Optimistic updates: add/remove updates the local Set immediately, then
 * fires the API call in the background. Failures are logged but not
 * surfaced — wishlist actions should feel instant.
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { useAuth } from './AuthProvider';
import {
  readGuestWishlist,
  writeGuestWishlist,
  clearGuestWishlist,
  fetchServerWishlist,
  addServerWishlist,
  removeServerWishlist,
  syncServerWishlist,
  type WishlistItem,
} from '@/lib/wishlist';

interface WishlistState {
  ids: Set<number>;
  items: WishlistItem[];          // populated only when authenticated; guests get [].
  loading: boolean;
  has: (productId: number) => boolean;
  toggle: (productId: number) => void;
  add: (productId: number) => void;
  remove: (productId: number) => void;
  refresh: () => Promise<void>;
}

const WishlistContext = createContext<WishlistState>({
  ids: new Set(),
  items: [],
  loading: false,
  has: () => false,
  toggle: () => {},
  add: () => {},
  remove: () => {},
  refresh: async () => {},
});

export function useWishlist() {
  return useContext(WishlistContext);
}

export function WishlistProvider({ children }: { children: ReactNode }) {
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const [ids, setIds] = useState<Set<number>>(new Set());
  const [items, setItems] = useState<WishlistItem[]>([]);
  const [loading, setLoading] = useState(false);
  // Track auth state across renders so we can detect the guest→auth transition
  const wasAuthRef = useRef<boolean | null>(null);

  const refresh = useCallback(async () => {
    if (token) {
      try {
        const data = await fetchServerWishlist(token);
        setItems(data.items);
        setIds(new Set(data.items.map((i) => i.product_id)));
      } catch {
        // network failure → keep what we have, don't blank the UI
      }
    } else {
      const guest = readGuestWishlist();
      setIds(new Set(guest));
      setItems([]);
    }
  }, [token]);

  // Initial load + auth transitions
  useEffect(() => {
    if (authLoading) return;

    const wasAuth = wasAuthRef.current;
    wasAuthRef.current = isLoggedIn;

    // Guest → Auth transition: merge localStorage into server then clear
    if (token && wasAuth === false) {
      const guestIds = readGuestWishlist();
      setLoading(true);
      (async () => {
        try {
          if (guestIds.length > 0) {
            await syncServerWishlist(token, guestIds);
            clearGuestWishlist();
          }
          await refresh();
        } finally {
          setLoading(false);
        }
      })();
      return;
    }

    // Logout: drop server items but keep localStorage (will become the new guest list)
    if (!token && wasAuth === true) {
      setItems([]);
      // ids stays as last-known set so the heart doesn't flash; will be replaced by refresh()
    }

    setLoading(true);
    refresh().finally(() => setLoading(false));
  }, [token, isLoggedIn, authLoading, refresh]);

  const has = useCallback((productId: number) => ids.has(productId), [ids]);

  const add = useCallback((productId: number) => {
    setIds((prev) => {
      if (prev.has(productId)) return prev;
      const next = new Set(prev);
      next.add(productId);
      if (!token) writeGuestWishlist(Array.from(next));
      return next;
    });
    if (token) addServerWishlist(token, productId).catch(() => {});
  }, [token]);

  const remove = useCallback((productId: number) => {
    setIds((prev) => {
      if (!prev.has(productId)) return prev;
      const next = new Set(prev);
      next.delete(productId);
      if (!token) writeGuestWishlist(Array.from(next));
      return next;
    });
    setItems((prev) => prev.filter((i) => i.product_id !== productId));
    if (token) removeServerWishlist(token, productId).catch(() => {});
  }, [token]);

  const toggle = useCallback((productId: number) => {
    if (ids.has(productId)) remove(productId);
    else add(productId);
  }, [ids, add, remove]);

  const value = useMemo<WishlistState>(() => ({
    ids, items, loading, has, toggle, add, remove, refresh,
  }), [ids, items, loading, has, toggle, add, remove, refresh]);

  return <WishlistContext.Provider value={value}>{children}</WishlistContext.Provider>;
}
