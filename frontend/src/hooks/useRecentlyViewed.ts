'use client';

/**
 * Client-only hook for "recently viewed products" — persists to localStorage.
 * Max 12 items, newest first. No backend. Safe to call with slug=undefined
 * (skips tracking without crashing during SSR/hydration).
 */

import { useEffect, useState } from 'react';

const STORAGE_KEY = 'pandora-recently-viewed';
const MAX_ITEMS = 12;

export interface RecentItem {
  slug: string;
  name: string;
  image: string | null;
  price: number;
}

function readStorage(): RecentItem[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const arr = JSON.parse(raw);
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

function writeStorage(list: RecentItem[]) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(list.slice(0, MAX_ITEMS)));
  } catch {
    /* quota ignored */
  }
}

/**
 * Track a product in the list. Call once per product-detail mount.
 */
export function trackRecentlyViewed(item: RecentItem) {
  if (!item?.slug) return;
  const current = readStorage().filter((i) => i.slug !== item.slug);
  writeStorage([item, ...current]);
}

/**
 * Read the list reactively (e.g. for widgets).
 * Excludes a slug if provided (useful on product detail to hide current product).
 */
export function useRecentlyViewed(excludeSlug?: string): RecentItem[] {
  const [items, setItems] = useState<RecentItem[]>([]);
  useEffect(() => {
    const list = readStorage().filter((i) => i.slug !== excludeSlug);
    setItems(list);
  }, [excludeSlug]);
  return items;
}
