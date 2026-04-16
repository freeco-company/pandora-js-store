'use client';

/**
 * Mobile-only sticky CTA for product detail.
 * Layout: cart-icon shortcut · qty stepper · big 加入購物車 button.
 */

import Link from 'next/link';
import { useState } from 'react';
import type { Product } from '@/lib/api';
import { useCart } from './CartProvider';
import { useToast } from './Toast';

export default function ProductStickyCTA({ product }: { product: Product }) {
  const { addToCart } = useCart();
  const { toast } = useToast();
  const [qty, setQty] = useState(1);
  const soldOut = product.stock_status === 'outofstock';

  const handleAdd = () => {
    if (soldOut) return;
    addToCart(product, qty);
    toast('已加入購物車');
  };

  return (
    <div
      className="md:hidden fixed bottom-0 left-0 right-0 z-[70] bg-white/95 backdrop-blur-xl border-t border-[#e7d9cb] shadow-[0_-8px_30px_rgba(159,107,62,0.15)]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 0)' }}
    >
      <div className="flex items-center gap-2 px-3 py-3">
        {/* Go-to-cart shortcut */}
        <Link
          href="/cart"
          aria-label="前往購物車"
          className="shrink-0 w-12 h-14 rounded-2xl bg-[#fdf7ef] border border-[#e7d9cb] flex items-center justify-center active:scale-95 transition-transform"
        >
          <svg fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="w-5 h-5 text-[#9F6B3E]">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
          </svg>
        </Link>

        {/* Qty stepper */}
        <div className="shrink-0 inline-flex items-center h-14 rounded-2xl bg-white border border-[#e7d9cb] overflow-hidden">
          <button
            onClick={() => setQty(Math.max(1, qty - 1))}
            className="w-11 h-full flex items-center justify-center text-gray-600 active:bg-[#fdf7ef] active:text-[#9F6B3E]"
            aria-label="減少"
            disabled={soldOut}
          >
            <svg fill="none" viewBox="0 0 24 24" strokeWidth={2.4} stroke="currentColor" className="w-5 h-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14" />
            </svg>
          </button>
          <span className="min-w-[32px] text-center font-black text-gray-900 text-base">{qty}</span>
          <button
            onClick={() => setQty(qty + 1)}
            className="w-11 h-full flex items-center justify-center text-gray-600 active:bg-[#fdf7ef] active:text-[#9F6B3E]"
            aria-label="增加"
            disabled={soldOut}
          >
            <svg fill="none" viewBox="0 0 24 24" strokeWidth={2.4} stroke="currentColor" className="w-5 h-5">
              <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </button>
        </div>

        {/* Primary CTA fills remaining space */}
        <button
          onClick={handleAdd}
          disabled={soldOut}
          className={`flex-1 h-14 rounded-2xl text-base font-black transition-all active:scale-[0.98] ${
            soldOut
              ? 'bg-gray-200 text-gray-500 cursor-not-allowed'
              : 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-lg shadow-[#9F6B3E]/25'
          }`}
        >
          {soldOut ? '已售完' : '加入購物車'}
        </button>
      </div>
    </div>
  );
}
