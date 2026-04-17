'use client';

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from 'react';
import type { Product } from '@/lib/api';
import {
  calculateCartLocally,
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
  itemPrices: { productId: number; unitPrice: number; subtotal: number }[];
  addToCart: (product: Product, quantity?: number) => void;
  removeFromCart: (productId: number) => void;
  updateQuantity: (productId: number, quantity: number) => void;
  clearCart: () => void;
}

const CartContext = createContext<CartContextValue | null>(null);

const CART_STORAGE_KEY = 'pandora-cart';

interface StoredCartItem {
  product: Product;
  quantity: number;
}

export function CartProvider({ children }: { children: ReactNode }) {
  const [items, setItems] = useState<LocalCartItem[]>([]);
  const [hydrated, setHydrated] = useState(false);

  // Load cart from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(CART_STORAGE_KEY);
      if (stored) {
        const parsed: StoredCartItem[] = JSON.parse(stored);
        setItems(parsed);
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
      const existing = prev.find((i) => i.product.id === product.id);
      if (existing) {
        return prev.map((i) =>
          i.product.id === product.id
            ? { ...i, quantity: i.quantity + quantity }
            : i
        );
      }
      return [...prev, { product, quantity }];
    });

    trackAddToCart(product.name, product.price, product.id, quantity);

    // Fire-and-forget activation marker for logged-in users
    try {
      const token = typeof window !== 'undefined' ? localStorage.getItem('pandora-auth-token') : null;
      if (token) markActivation(token, 'first_cart').catch(() => {});
    } catch {
      // ignore
    }
  }, []);

  const removeFromCart = useCallback((productId: number) => {
    setItems((prev) => prev.filter((i) => i.product.id !== productId));
  }, []);

  const updateQuantity = useCallback((productId: number, quantity: number) => {
    if (quantity <= 0) {
      setItems((prev) => prev.filter((i) => i.product.id !== productId));
      return;
    }
    setItems((prev) =>
      prev.map((i) =>
        i.product.id === productId ? { ...i, quantity } : i
      )
    );
  }, []);

  const clearCart = useCallback(() => {
    setItems([]);
  }, []);

  const { tier, total, itemPrices } = calculateCartLocally(items);
  const itemCount = items.reduce((sum, i) => sum + i.quantity, 0);

  return (
    <CartContext.Provider
      value={{
        items,
        tier,
        total,
        itemCount,
        itemPrices,
        addToCart,
        removeFromCart,
        updateQuantity,
        clearCart,
      }}
    >
      {children}
    </CartContext.Provider>
  );
}

export function useCart() {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error('useCart must be used within a CartProvider');
  }
  return context;
}
