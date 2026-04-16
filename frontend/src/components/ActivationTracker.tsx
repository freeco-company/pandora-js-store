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

const CACHE_KEY = 'pandora-activation-marked';

function matchStep(pathname: string): ActivationStep | null {
  if (pathname === '/products' || pathname.startsWith('/products?')) return 'first_browse';
  if (pathname.startsWith('/products/')) return 'first_browse';
  if (pathname.startsWith('/articles')) return 'first_article';
  if (pathname.startsWith('/about')) return 'first_brand';
  if (pathname.startsWith('/account/mascot')) return 'first_mascot';
  return null;
}

function readCache(): Record<string, boolean> {
  if (typeof window === 'undefined') return {};
  try {
    return JSON.parse(localStorage.getItem(CACHE_KEY) || '{}');
  } catch {
    return {};
  }
}

function writeCache(cache: Record<string, boolean>) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(cache));
  } catch {
    // ignore quota errors
  }
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

    // Skip if already fired this session + this step
    const fireKey = `${token.slice(0, 8)}:${step}`;
    if (lastFired.current === fireKey) return;

    const cache = readCache();
    if (cache[step]) {
      lastFired.current = fireKey;
      return;
    }

    lastFired.current = fireKey;
    markActivation(token, step)
      .then((res) => {
        const c = readCache();
        c[step] = true;
        writeCache(c);
        // First-time award → fullscreen celebration modal
        if (res._achievement) celebrate(res._achievement);
      })
      .catch(() => {
        lastFired.current = '';
      });
  }, [pathname, token, isLoggedIn, celebrate]);

  return null;
}
