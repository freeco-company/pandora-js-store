'use client';

import { useState, useEffect } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { useCart } from '@/components/CartProvider';
import { tierLabel, getPrice } from '@/lib/pricing';
import { formatPrice } from '@/lib/format';
import { imageUrl, getProducts, type Product } from '@/lib/api';
import CartStickyCTA from '@/components/CartStickyCTA';

const VIP_THRESHOLD = 4000;

export default function CartPage() {
  const { items, tier, total, itemPrices, itemCount, addToCart, updateQuantity, removeFromCart, clearCart } = useCart();
  const [showClearConfirm, setShowClearConfirm] = useState(false);
  const [removeConfirmId, setRemoveConfirmId] = useState<number | null>(null);
  const [relatedProducts, setRelatedProducts] = useState<Product[]>([]);

  const totalQuantity = items.reduce((sum, i) => sum + i.quantity, 0);

  // Calculate upgrade hints
  const comboTotal = items.reduce(
    (sum, i) => sum + getPrice(i.product, 'combo') * i.quantity,
    0
  );
  const amountToVip = VIP_THRESHOLD - comboTotal;
  const vipProgress = Math.min((comboTotal / VIP_THRESHOLD) * 100, 100);

  // Calculate savings vs regular price
  const regularTotal = items.reduce(
    (sum, i) => sum + i.product.price * i.quantity,
    0
  );
  const savings = regularTotal - total;

  // Fetch related products (exclude those already in cart)
  useEffect(() => {
    const cartIds = new Set(items.map((i) => i.product.id));
    getProducts()
      .then((all) => setRelatedProducts(all.filter((p) => !cartIds.has(p.id)).slice(0, 4)))
      .catch(() => {});
  }, [items]);

  if (items.length === 0) {
    return (
      <div className="max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-16 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-20 h-20 mx-auto text-gray-300 mb-6">
          <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
        <h1 className="text-2xl font-bold text-gray-900 mb-2">購物車是空的</h1>
        <p className="text-gray-500 mb-6">快去挑選喜歡的商品吧！</p>
        <Link
          href="/products"
          className="inline-flex items-center px-8 py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
        >
          前往選購
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-8 sm:py-12 pb-[calc(6rem+env(safe-area-inset-bottom))] md:pb-12">
      <div className="flex items-center justify-between mb-8">
        <h1 className="text-2xl sm:text-3xl font-bold text-gray-900">
          購物車 ({itemCount})
        </h1>
        <button
          onClick={() => setShowClearConfirm(true)}
          className="text-sm text-gray-500 hover:text-red-500 transition-colors"
        >
          清空購物車
        </button>
      </div>

      {/* 自用加盟 upsell — shown when cart non-empty and not VIP */}
      {tier !== 'vip' && total >= 500 && (
        <Link
          href="/join"
          className="block mb-4 p-4 rounded-2xl bg-gradient-to-br from-[#fef6e4] to-[#fbe4b0] border border-[#E8A93B]/40 hover:border-[#E8A93B] hover:shadow-md transition-all group"
        >
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-[#E8A93B] flex items-center justify-center shrink-0 text-lg">🌱</div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-black text-[#7a5836]">加盟會員價更省 · 一次 NT$6,600 永久享優惠</div>
              <div className="text-[11px] text-[#7a5836]/80 mt-0.5">了解自用加盟方案 →</div>
            </div>
            <span className="text-[#7a5836] group-hover:translate-x-1 transition-transform">→</span>
          </div>
        </Link>
      )}

      {/* Current Tier Badge */}
      <div className="bg-gradient-to-r from-[#9F6B3E]/10 to-[#9F6B3E]/5 rounded-[10px] p-4 mb-6">
        <div className="flex items-center justify-between mb-2 flex-wrap gap-2">
          <span className="text-sm font-medium text-gray-700">目前價格方案</span>
          <div className="flex items-center gap-2">
            {savings > 0 && (
              <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-600 text-xs font-black rounded-full border border-red-200">
                已省 {formatPrice(savings)}
              </span>
            )}
            <span className="text-lg font-bold text-[#9F6B3E]">{tierLabel(tier)}</span>
          </div>
        </div>

        {/* Upgrade hints */}
        {tier === 'regular' && totalQuantity === 1 && (
          <p className="text-sm text-gray-600">
            再加 <strong>1 件</strong>即享 1+1 搭配價！
          </p>
        )}

        {tier === 'combo' && amountToVip > 0 && (() => {
          // Estimate additional savings if upgraded to VIP tier
          const vipTotalEstimate = items.reduce(
            (sum, i) => sum + getPrice(i.product, 'vip') * i.quantity,
            0
          );
          const extraSavings = Math.max(0, comboTotal - vipTotalEstimate);
          return (
            <div>
              <p className="text-sm text-gray-600 mb-2">
                再加 <strong className="text-[#9F6B3E]">{formatPrice(Math.ceil(amountToVip))}</strong> 即可升級 VIP 優惠價
                {extraSavings > 0 && (
                  <span className="text-red-500 font-bold"> · 再省 {formatPrice(extraSavings)}！</span>
                )}
              </p>
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div
                  className="bg-[#9F6B3E] h-2 rounded-full transition-all duration-500"
                  style={{ width: `${vipProgress}%` }}
                />
              </div>
              <div className="flex justify-between text-xs text-gray-500 mt-1">
                <span>{formatPrice(comboTotal)}</span>
                <span>{formatPrice(VIP_THRESHOLD)}</span>
              </div>
            </div>
          );
        })()}

        {tier === 'vip' && (
          <p className="text-sm text-green-700 font-medium">
            已享有最高等級 VIP 優惠價！
          </p>
        )}
      </div>

      {/* Cart Items */}
      <div className="space-y-4 mb-8">
        {items.map((item) => {
          const priceInfo = itemPrices.find((p) => p.productId === item.product.id);
          const unitPrice = priceInfo?.unitPrice ?? item.product.price;
          const subtotal = priceInfo?.subtotal ?? item.product.price * item.quantity;
          const hasDiscount = unitPrice < item.product.price;

          return (
            <div
              key={item.product.id}
              className="flex gap-4 p-4 bg-white rounded-[10px]"
              style={{
                border: '1px solid rgba(0, 0, 0, 0.05)',
                boxShadow: '0px 12px 18px -6px rgba(34, 56, 101, 0.04)',
              }}
            >
              {/* Image */}
              <div className="relative w-20 h-20 sm:w-24 sm:h-24 bg-gray-50 rounded-lg overflow-hidden shrink-0">
                {item.product.image ? (
                  <Image
                    src={imageUrl(item.product.image)!}
                    alt={item.product.name}
                    fill
                    sizes="96px"
                    className="object-cover"
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-gray-300">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-8 h-8">
                      <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                    </svg>
                  </div>
                )}
              </div>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <Link
                  href={`/products/${item.product.slug}`}
                  className="font-semibold text-gray-900 hover:text-[#9F6B3E] transition-colors line-clamp-1"
                >
                  {item.product.name}
                </Link>

                <div className="mt-1 flex items-baseline gap-2">
                  <span className="font-semibold text-[#9F6B3E]">{formatPrice(unitPrice)}</span>
                  {hasDiscount && (
                    <span className="text-sm text-gray-400 line-through">{formatPrice(item.product.price)}</span>
                  )}
                </div>

                {/* Quantity Controls */}
                <div className="flex items-center gap-3 mt-2">
                  <div className="flex items-center border border-gray-200 rounded-full">
                    <button
                      onClick={() => updateQuantity(item.product.id, item.quantity - 1)}
                      className="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-gray-900"
                      aria-label="減少"
                    >
                      -
                    </button>
                    <span className="w-8 text-center text-sm font-medium">
                      {item.quantity}
                    </span>
                    <button
                      onClick={() => updateQuantity(item.product.id, item.quantity + 1)}
                      className="w-8 h-8 flex items-center justify-center text-gray-600 hover:text-gray-900"
                      aria-label="增加"
                    >
                      +
                    </button>
                  </div>
                  <button
                    onClick={() => setRemoveConfirmId(item.product.id)}
                    className="text-xs text-gray-400 hover:text-red-500 transition-colors min-h-[32px] px-2"
                  >
                    移除
                  </button>
                </div>
              </div>

              {/* Subtotal */}
              <div className="text-right shrink-0">
                <span className="font-bold text-gray-900">{formatPrice(subtotal)}</span>
              </div>
            </div>
          );
        })}
      </div>

      {/* Summary — breakdown always, CTA button only on desktop (mobile uses sticky) */}
      <div className="bg-gray-50 rounded-[10px] p-6">
        <div className="space-y-3">
          <div className="flex justify-between text-sm">
            <span className="text-gray-600">商品原價小計</span>
            <span className="text-gray-900">{formatPrice(regularTotal)}</span>
          </div>
          {savings > 0 && (
            <div className="flex justify-between text-sm">
              <span className="text-gray-600">搭配優惠折抵</span>
              <span className="text-red-500 font-medium">-{formatPrice(savings)}</span>
            </div>
          )}
          <div className="flex justify-between text-sm">
            <span className="text-gray-600">運費</span>
            <span className="text-green-600 font-medium">免運</span>
          </div>
          <div className="flex justify-between text-sm">
            <span className="text-gray-600">適用方案</span>
            <span className="text-[#9F6B3E] font-medium">{tierLabel(tier)}</span>
          </div>
          <div className="border-t border-gray-200 pt-3 flex justify-between">
            <span className="text-lg font-bold text-gray-900">合計</span>
            <span className="text-lg font-bold text-[#9F6B3E]">{formatPrice(total)}</span>
          </div>
          {savings > 0 && (
            <p className="text-sm text-red-500 text-right font-medium">
              已省下 {formatPrice(savings)}！
            </p>
          )}
        </div>

        {/* Desktop CTA — mobile has sticky bottom bar */}
        <Link
          href="/checkout"
          className="hidden md:block mt-6 w-full py-3 bg-[#9F6B3E] text-white text-center font-semibold rounded-full hover:bg-[#85572F] transition-colors"
        >
          前往結帳
        </Link>
      </div>

      {/* Related Products */}
      {relatedProducts.length > 0 && (
        <div className="mt-10">
          <h2 className="text-xl font-bold text-gray-900 mb-4">加購其他商品</h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
            {relatedProducts.map((product) => {
              const showCombo = totalQuantity >= 1 && product.combo_price;
              return (
                <div
                  key={product.id}
                  className="bg-white rounded-[10px] overflow-hidden"
                  style={{
                    border: '1px solid rgba(0, 0, 0, 0.05)',
                    boxShadow: '0px 12px 18px -6px rgba(34, 56, 101, 0.04)',
                  }}
                >
                  <Link href={`/products/${product.slug}`} className="block">
                    <div className="relative aspect-square bg-gray-50 overflow-hidden">
                      {product.image ? (
                        <Image
                          src={imageUrl(product.image)!}
                          alt={product.name}
                          fill
                          sizes="(max-width: 640px) 50vw, 25vw"
                          className="object-cover"
                        />
                      ) : (
                        <div className="w-full h-full flex items-center justify-center text-gray-300">
                          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-12 h-12">
                            <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                          </svg>
                        </div>
                      )}
                    </div>
                  </Link>
                  <div className="p-2.5">
                    <h3 className="text-xs font-semibold text-gray-900 line-clamp-2 mb-1">
                      {product.name}
                    </h3>
                    <div className="flex items-baseline gap-1 mb-2">
                      {showCombo ? (
                        <>
                          <span className="text-sm font-bold text-[#9F6B3E]">{formatPrice(product.combo_price)}</span>
                          <span className="text-xs text-gray-400 line-through">{formatPrice(product.price)}</span>
                        </>
                      ) : (
                        <span className="text-sm font-bold text-[#9F6B3E]">{formatPrice(product.price)}</span>
                      )}
                    </div>
                    <button
                      onClick={() => addToCart(product)}
                      className="w-full py-1.5 text-xs font-semibold rounded-full bg-[#9F6B3E] text-white hover:bg-[#85572F] transition-colors"
                    >
                      加入購物車
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Remove Item Confirmation Dialog */}
      {removeConfirmId !== null && (() => {
        const item = items.find((i) => i.product.id === removeConfirmId);
        return (
          <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 modal-overlay-in"
            onClick={() => setRemoveConfirmId(null)}
          >
            <div
              className="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl modal-content-pop"
              onClick={(e) => e.stopPropagation()}
            >
              <h3 className="text-lg font-bold text-gray-900 mb-2">移除商品</h3>
              <p className="text-gray-600 mb-6">
                確定要從購物車移除「<strong className="text-gray-900">{item?.product.name}</strong>」嗎？
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => setRemoveConfirmId(null)}
                  className="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-full hover:bg-gray-50 transition-colors min-h-[44px]"
                >
                  取消
                </button>
                <button
                  onClick={() => {
                    removeFromCart(removeConfirmId);
                    setRemoveConfirmId(null);
                  }}
                  className="flex-1 py-2.5 bg-red-500 text-white font-medium rounded-full hover:bg-red-600 transition-colors min-h-[44px]"
                >
                  確定移除
                </button>
              </div>
            </div>
          </div>
        );
      })()}

      {/* Clear Cart Confirmation Dialog */}
      {showClearConfirm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 modal-overlay-in" onClick={() => setShowClearConfirm(false)}>
          <div className="bg-white rounded-3xl p-6 max-w-sm w-full shadow-2xl modal-content-pop" onClick={(e) => e.stopPropagation()}>
            <h3 className="text-lg font-bold text-gray-900 mb-2">清空購物車</h3>
            <p className="text-gray-600 mb-6">確定要清空購物車嗎？</p>
            <div className="flex gap-3">
              <button
                onClick={() => setShowClearConfirm(false)}
                className="flex-1 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-full hover:bg-gray-50 transition-colors"
              >
                取消
              </button>
              <button
                onClick={() => {
                  clearCart();
                  setShowClearConfirm(false);
                }}
                className="flex-1 py-2.5 bg-red-500 text-white font-medium rounded-full hover:bg-red-600 transition-colors"
              >
                確定
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Mobile sticky CTA — bottom-fixed, shows total/savings/checkout */}
      <CartStickyCTA />
    </div>
  );
}
