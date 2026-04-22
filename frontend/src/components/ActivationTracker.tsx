'use client';

/**
 * Automatically marks activation steps based on visited pages.
 * Fires once per step per session (localStorage cache) for logged-in users.
 *
 * Mounted once globally in the layout so every route change checks.
 */

import { usePathname } from 'next/navigation';
import { useEffect, useRef } from 'react';
import { useAuth } from './AuthProvider';
import { useCelebrate } from './Celebration';
import { markActivation, type ActivationStep } from '@/lib/api';

// Cache is scoped by user token prefix so switching accounts on the same
// browser doesn't cross-contaminate. Previously a single CACHE_KEY was
// shared, causing the bug where a new user's tasks were skipped because
// a prior user had already marked them on that device.
const CACHE_KEY_PREFIX = 'pandora-activation-marked:';

function matchStep(pathname: string): ActivationStep | null {
  if (pathname === '/products' || pathname.startsWith('/products?')) return 'first_browse';
  if (pathname.startsWith('/products/')) return 'first_browse';
  if (pathname.startsWith('/articles')) return 'first_article';
  if (pathname.startsWith('/about')) return 'first_brand';
  if (pathname.startsWith('/account/mascot')) return 'first_mascot';
  return null;
}

function cacheKey(token: string): string {
  return CACHE_KEY_PREFIX + token.slice(0, 16);
}

function readCache(token: string): Record<string, boolean> {
  if (typeof window === 'undefined') return {};
  try {
    return JSON.parse(localStorage.getItem(cacheKey(token)) || '{}');
  } catch {
    return {};
  }
}

function writeCache(token: string, cache: Record<string, boolean>): void {
  try {
    localStorage.setItem(cacheKey(token), JSON.stringify(cache));
  } catch {
    // ignore quota errors
  }
}

/**
 * Delete the legacy shared cache. We deliberately don't migrate it to a
 * user scope: "first user we see after deploy owns this cache" is only
 * safe when we can identify that user, and we can't — the cache had no
 * identity. Worst case after delete: one extra markActivation API call
 * per step per user. Backend is idempotent, so no correctness risk.
 */
function clearLegacyCache(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem('pandora-activation-marked');
}

export default function ActivationTracker() {
  const pathname = usePathname();
  const { token, isLoggedIn } = useAuth();
  const { celebrate } = useCelebrate();
  const lastFired = useRef<string>('');

  useEffect(() => {
    if (!isLoggedIn || !token) return;
    const step = matchStep(pathname);
    if (!step) return;

    clearLegacyCache();

    // Dedup within this mount + token combo
    const fireKey = `${token.slice(0, 8)}:${step}`;
    if (lastFired.current === fireKey) return;

    // Dedup across sessions via user-scoped localStorage cache
    const cache = readCache(token);
    if (cache[step]) {
      lastFired.current = fireKey;
      return;
    }

    lastFired.current = fireKey;
    markActivation(token, step)
      .then((res) => {
        const c = readCache(token);
        c[step] = true;
        writeCache(token, c);
        // First-time award → fullscreen celebration modal
        if (res._achievement) celebrate(res._achievement);
      })
      .catch(() => {
        lastFired.current = '';
      });
  }, [pathname, token, isLoggedIn, celebrate]);

  return null;
}
