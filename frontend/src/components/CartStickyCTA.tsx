'use client';

/**
 * Mobile-only sticky CTA for /cart.
 * Shows: 合計 + 省多少 (if > 0) + 前往結帳 button.
 * Desktop renders nothing — desktop cart has its own summary panel.
 */

import Link from 'next/link';
import { useCart } from './CartProvider';
import { getPrice } from '@/lib/pricing';
import { formatPrice } from '@/lib/format';

export default function CartStickyCTA({ hasUnavailable = false }: { hasUnavailable?: boolean }) {
  const { items, total, itemCount } = useCart();

  if (itemCount === 0) return null;

  const regularTotal = items.reduce(
    (sum, i) => sum + getPrice(i.product, 'regular') * i.quantity,
    0,
  );
  const savings = Math.max(0, regularTotal - total);

  return (
    <div
      className="md:hidden fixed bottom-0 left-0 right-0 z-[70] bg-white/95 backdrop-blur-xl border-t border-[#e7d9cb] shadow-[0_-8px_30px_rgba(159,107,62,0.12)]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 0)' }}
    >
      <div className="flex items-center gap-3 px-3 py-2.5">
        <div className="flex-1 min-w-0">
          <div className="flex items-baseline gap-2">
            <span className="text-[10px] font-black text-gray-500 tracking-wider">合計</span>
            <span className="text-lg font-black text-[#9F6B3E] truncate">{formatPrice(total)}</span>
          </div>
          {savings > 0 && (
            <div className="text-[10px] font-black text-red-500 leading-tight mt-0.5">
              已省 {formatPrice(savings)}
            </div>
          )}
        </div>
        {hasUnavailable ? (
          <span className="shrink-0 h-11 px-6 rounded-full bg-gray-300 text-white font-black text-sm flex items-center gap-1.5 cursor-not-allowed">
            請先移除無法購買的商品
          </span>
        ) : (
          <Link
            href="/checkout"
            className="shrink-0 h-11 px-6 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm flex items-center gap-1.5 shadow-md shadow-[#9F6B3E]/20 active:scale-[0.98] transition-transform"
          >
            前往結帳
            <span aria-hidden>→</span>
          </Link>
        )}
      </div>
    </div>
  );
}
