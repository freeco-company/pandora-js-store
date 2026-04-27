'use client';

/**
 * Mobile-only sticky CTA for /cart.
 * Shows: 合計 + 省多少 (if > 0) + 前往結帳 button.
 * Desktop renders nothing — desktop cart has its own summary panel.
 */

import Link from 'next/link';
import { useCart } from './CartProvider';
import { getPrice, isProductItem, isBundleItem } from '@/lib/pricing';
import { formatPrice } from '@/lib/format';

const MEMBER_TRAINEE_THRESHOLD = 6600;
const LINE_OFFICIAL_URL = 'https://lin.ee/62wj7qa';

export default function CartStickyCTA({ hasUnavailable = false }: { hasUnavailable?: boolean }) {
  const { items, total, itemCount } = useCart();

  if (itemCount === 0) return null;

  const regularTotal =
    items.filter(isProductItem).reduce(
      (sum, i) => sum + getPrice(i.product, 'regular') * i.quantity,
      0,
    ) +
    items.filter(isBundleItem).reduce(
      (sum, i) => sum + i.bundle.bundle_value_price * i.quantity,
      0,
    );
  const savings = Math.max(0, regularTotal - total);
  const showLineCta = !hasUnavailable && total >= MEMBER_TRAINEE_THRESHOLD;

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
        ) : showLineCta ? (
          <div className="flex items-center gap-2 shrink-0">
            <a
              href={LINE_OFFICIAL_URL}
              target="_blank"
              rel="noopener"
              aria-label="LINE 專屬客服啟用"
              className="h-11 w-11 rounded-full bg-white border-2 border-[#06C755] text-[#06C755] flex items-center justify-center active:scale-[0.95] transition-transform"
            >
              <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden>
                <path d="M19.365 9.863c.349 0 .63.283.63.631 0 .347-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .348-.281.63-.63.63h-2.386c-.345 0-.627-.283-.627-.63V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .348-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.63V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.63V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.63V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
              </svg>
            </a>
            <Link
              href="/checkout"
              className="h-11 px-5 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm flex items-center gap-1.5 shadow-md shadow-[#9F6B3E]/20 active:scale-[0.98] transition-transform"
            >
              結帳
              <span aria-hidden>→</span>
            </Link>
          </div>
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
