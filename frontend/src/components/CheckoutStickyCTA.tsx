'use client';

/**
 * Mobile-only sticky CTA for /checkout.
 * Shows: final total + 確認送出訂單 button that triggers the external form.
 * The button uses HTML5 `form="checkout-form"` to submit the form from outside its tree.
 */

import { useCart } from './CartProvider';
import { formatPrice } from '@/lib/format';
import { getPrice, isProductItem, isBundleItem } from '@/lib/pricing';

export default function CheckoutStickyCTA({
  submitting,
  termsAgreed = false,
  formId = 'checkout-form',
}: {
  submitting: boolean;
  termsAgreed?: boolean;
  formId?: string;
}) {
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

  return (
    <div
      className="md:hidden fixed bottom-0 left-0 right-0 z-[70] bg-white/95 backdrop-blur-xl border-t border-[#e7d9cb] shadow-[0_-8px_30px_rgba(159,107,62,0.12)]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom, 0)' }}
    >
      <div className="flex items-center gap-3 px-3 py-2.5">
        <div className="flex-1 min-w-0">
          <div className="flex items-baseline gap-2">
            <span className="text-[10px] font-black text-gray-500 tracking-wider">應付</span>
            <span className="text-lg font-black text-[#9F6B3E] truncate">{formatPrice(total)}</span>
          </div>
          {savings > 0 && (
            <div className="text-[10px] font-black text-red-500 leading-tight mt-0.5">
              已省 {formatPrice(savings)}
            </div>
          )}
        </div>
        <button
          type="submit"
          form={formId}
          disabled={submitting || !termsAgreed}
          className="shrink-0 h-11 px-6 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm flex items-center gap-1.5 shadow-md shadow-[#9F6B3E]/20 active:scale-[0.98] transition-transform disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {submitting ? '處理中...' : !termsAgreed ? '請先同意條款' : '送出訂單'}
          {!submitting && <span aria-hidden>→</span>}
        </button>
      </div>
    </div>
  );
}
