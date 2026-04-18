'use client';

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useMemo,
  type ReactNode,
} from 'react';
import type { Product, CampaignBundle } from '@/lib/api';
import {
  calculateCartLocally,
  isBundleItem,
  isProductItem,
  type LocalCartItem,
  type PricingTier,
} from '@/lib/pricing';
import { markActivation } from '@/lib/api';
import { trackAddToCart } from '@/components/Analytics';

interface CartContextValue {
  items: LocalCartItem[];
  tier: PricingTier;
  total: number;
  itemCount: number;
  itemPrices: { key: string; unitPrice: number; subtotal: number }[];
  addToCart: (product: Product, quantity?: number) => void;
  addBundle: (bundle: CampaignBundle, quantity?: number) => void;
  removeFromCart: (productId: number) => void;
  removeBundle: (campaignId: number) => void;
  updateQuantity: (productId: number, quantity: number) => void;
  updateBundleQuantity: (campaignId: number, quantity: number) => void;
  clearCart: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);

const CART_STORAGE_KEY = 'pandora-cart';

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<LocalCartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  // Load cart from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(CART_STORAGE_KEY);
      if (stored) {
        const parsed: LocalCartItem[] = JSON.parse(stored);
        // Filter out any item missing either product or bundle payload
        // (older cart shapes with partial fields would otherwise crash pricing)
        const valid = parsed
          .filter(
            (i) => (isBundleItem(i) && i.bundle) || (isProductItem(i) && i.product),
          )
          // Backfill fields added after a bundle was already saved to cart, so
          // rendering code can assume they exist (e.g. .length / .map).
          .map((i) =>
            isBundleItem(i)
              ? {
                  ...i,
                  bundle: {
                    ...i.bundle,
                    custom_gifts: i.bundle.custom_gifts ?? [],
                    gift_items: i.bundle.gift_items ?? [],
                    buy_items: i.bundle.buy_items ?? [],
                  },
                }
              : i,
          );
        setItems(valid);
      }
    } catch {
      // Ignore parse errors
    }
    setHydrated(true);
  }, []);

  // Save cart to localStorage on change
  useEffect(() => {
    if (hydrated) {
      localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
    }
  }, [items, hydrated]);

  const addToCart = useCallback((product: Product, quantity = 1) => {
    setItems((prev) => {
      const existing = prev.find(
        (i) => isProductItem(i) && i.product.id === product.id,
      );
      if (existing) {
        return prev.map((i) =>
          isProductItem(i) && i.product.id === product.id
            ? { ...i, quantity: i.quantity + quantity }
            : i,
        );
      }
      return [...prev, { type: 'product', product, quantity }];
    });

    trackAddToCart(product.name, product.price, product.id, quantity);

    try {
      const token = typeof window !== 'undefined' ? localStorage.getItem('pandora-auth-token') : null;
      if (token) markActivation(token, 'first_cart').catch(() => {});
    } catch {
      // ignore
    }
  }, []);

  const addBundle = useCallback((bundle: CampaignBundle, quantity = 1) => {
    setItems((prev) => {
      const existing = prev.find(
        (i) => isBundleItem(i) && i.bundle.id === bundle.id,
      );
      if (existing) {
        return prev.map((i) =>
          isBundleItem(i) && i.bundle.id === bundle.id
            ? { ...i, quantity: i.quantity + quantity }
            : i,
        );
      }
      return [...prev, { type: 'bundle', bundle, quantity }];
    });

    try {
      const token = typeof window !== 'undefined' ? localStorage.getItem('pandora-auth-token') : null;
      if (token) markActivation(token, 'first_cart').catch(() => {});
    } catch {
      // ignore
    }
  }, []);

  const removeFromCart = useCallback((productId: number) => {
    setItems((prev) => prev.filter((i) => !(isProductItem(i) && i.product.id === productId)));
  }, []);

  const removeBundle = useCallback((bundleId: number) => {
    setItems((prev) => prev.filter((i) => !(isBundleItem(i) && i.bundle.id === bundleId)));
  }, []);

  const updateQuantity = useCallback((productId: number, quantity: number) => {
    if (quantity <= 0) {
      setItems((prev) => prev.filter((i) => !(isProductItem(i) && i.product.id === productId)));
      return;
    }
    setItems((prev) =>
      prev.map((i) => (isProductItem(i) && i.product.id === productId ? { ...i, quantity } : i)),
    );
  }, []);

  const updateBundleQuantity = useCallback((bundleId: number, quantity: number) => {
    if (quantity <= 0) {
      setItems((prev) => prev.filter((i) => !(isBundleItem(i) && i.bundle.id === bundleId)));
      return;
    }
    setItems((prev) =>
      prev.map((i) => (isBundleItem(i) && i.bundle.id === bundleId ? { ...i, quantity } : i)),
    );
  }, []);

  const clearCart = useCallback(() => {
    setItems([]);
  }, []);

  const pricing = useMemo(() => calculateCartLocally(items), [items]);
  const itemCount = useMemo(() => items.reduce((sum, i) => sum + i.quantity, 0), [items]);

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      tier: pricing.tier,
      total: pricing.total,
      itemCount,
      itemPrices: pricing.itemPrices,
      addToCart,
      addBundle,
      removeFromCart,
      removeBundle,
      updateQuantity,
      updateBundleQuantity,
      clearCart,
    }),
    [
      items,
      pricing,
      itemCount,
      addToCart,
      addBundle,
      removeFromCart,
      removeBundle,
      updateQuantity,
      updateBundleQuantity,
      clearCart,
    ],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}
