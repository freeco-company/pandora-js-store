'use client';

/**
 * Cross-sell add-on section for product detail page.
 * Shows related products with inline add-to-cart and a shared cart progress bar
 * indicating how close the user is to the next pricing tier.
 */

import { useState } from 'react';
import Link from 'next/link';
import { type Product, imageUrl } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import { formatPrice } from '@/lib/format';
import { useCart } from './CartProvider';
import { useToast } from './Toast';
import { flyToCart } from '@/lib/animations';

const VIP_THRESHOLD = 4000;

interface CrossSellAddOnProps {
  products: Product[];
}

export default function CrossSellAddOn({ products }: CrossSellAddOnProps) {
  const { items, tier, total, itemCount, addToCart } = useCart();
  const { toast } = useToast();
  const [addedIds, setAddedIds] = useState<Set<number>>(new Set());

  if (products.length === 0) return null;

  // Progress bar calculations
  const comboUnlocked = itemCount >= 2;
  const vipUnlocked = tier === 'vip';

  // For combo: need 2 items
  const comboProgress = Math.min(1, itemCount / 2);

  // For VIP: need combo total >= 4000
  const vipProgress = comboUnlocked ? Math.min(1, total / VIP_THRESHOLD) : 0;
  const vipRemaining = VIP_THRESHOLD - total;

  const handleAdd = (product: Product, e: React.MouseEvent) => {
    addToCart(product);
    flyToCart(e.currentTarget as HTMLElement);
    toast(`已加入：${product.name}`);
    setAddedIds((prev) => new Set(prev).add(product.id));
    setTimeout(() => {
      setAddedIds((prev) => {
        const next = new Set(prev);
        next.delete(product.id);
        return next;
      });
    }, 1500);
  };

  return (
    <section className="mt-12 pt-10 border-t border-gray-200">
      <div className="flex items-end justify-between mb-4">
        <div>
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">ADD-ON · 搭配加購</div>
          <h2 className="text-xl sm:text-2xl font-black text-gray-900 mt-1">一起帶更划算</h2>
        </div>
        <Link href="/products" className="text-sm font-black text-[#9F6B3E] hover:underline whitespace-nowrap">
          看全部 →
        </Link>
      </div>

      {/* Tier progress bar */}
      <div className="mb-6 rounded-2xl bg-[#fdf7ef] border border-[#e7d9cb] p-4">
        <div className="flex items-center justify-between text-[11px] font-black mb-2">
          <span className="text-gray-600">
            購物車 {itemCount} 件 · {formatPrice(total)}
          </span>
          {!comboUnlocked && (
            <span className="text-[#9F6B3E]">再加 {2 - itemCount} 件享搭配價</span>
          )}
          {comboUnlocked && !vipUnlocked && vipRemaining > 0 && (
            <span className="text-[#9F6B3E]">再 {formatPrice(vipRemaining)} 升 VIP 價</span>
          )}
          {vipUnlocked && (
            <span className="text-green-600">已享 VIP 最優惠!</span>
          )}
        </div>

        {/* Multi-segment progress */}
        <div className="relative h-2 rounded-full bg-[#e7d9cb]/60 overflow-hidden">
          {/* Combo segment (0-50%) */}
          <div
            className="absolute left-0 top-0 h-full rounded-full transition-all duration-500 ease-out"
            style={{
              width: `${comboProgress * 50}%`,
              background: comboUnlocked
                ? 'linear-gradient(90deg, #9F6B3E, #c8835a)'
                : 'linear-gradient(90deg, #d4b896, #c8835a)',
            }}
          />
          {/* VIP segment (50-100%) */}
          {comboUnlocked && (
            <div
              className="absolute top-0 h-full rounded-full transition-all duration-500 ease-out"
              style={{
                left: '50%',
                width: `${vipProgress * 50}%`,
                background: vipUnlocked
                  ? 'linear-gradient(90deg, #c8835a, #9F6B3E)'
                  : 'linear-gradient(90deg, #d4b896, #9F6B3E)',
              }}
            />
          )}
        </div>

        {/* Labels */}
        <div className="flex justify-between mt-1.5 text-[9px] text-gray-400">
          <span>單件</span>
          <span className={comboUnlocked ? 'text-[#9F6B3E] font-black' : ''}>搭配價 (2件)</span>
          <span className={vipUnlocked ? 'text-[#9F6B3E] font-black' : ''}>VIP ($4,000)</span>
        </div>
      </div>

      {/* Product cards — horizontal scroll on mobile, grid on desktop */}
      <div className="flex gap-3 overflow-x-auto scrollbar-hide pb-1 sm:grid sm:grid-cols-4 sm:overflow-visible">
        {products.map((p) => {
          const isAdded = addedIds.has(p.id);
          const inCart = items.some((i) => i.product.id === p.id);
          const isOutOfStock = p.stock_status === 'outofstock';

          return (
            <div
              key={p.id}
              className="shrink-0 w-[160px] sm:w-auto bg-white rounded-2xl border border-gray-100 overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300"
            >
              <Link href={`/products/${p.slug}`} className="block">
                <div className="relative aspect-square bg-gray-50 overflow-hidden">
                  {p.image ? (
                    <ImageWithFallback
                      src={imageUrl(p.image)!}
                      alt={p.name}
                      fill
                      sizes="(max-width: 640px) 160px, 25vw"
                      className="object-cover"
                    />
                  ) : (
                    <LogoPlaceholder />
                  )}
                  {inCart && (
                    <span className="absolute top-2 right-2 w-5 h-5 rounded-full bg-[#9F6B3E] flex items-center justify-center">
                      <svg className="w-3 h-3 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                    </span>
                  )}
                </div>
              </Link>
              <div className="p-2.5">
                <h3 className="text-xs font-bold text-gray-800 line-clamp-1 mb-1">{p.name}</h3>
                <div className="flex items-baseline gap-1.5 mb-2">
                  {p.combo_price ? (
                    <>
                      <span className="text-sm font-black text-[#9F6B3E]">{formatPrice(p.combo_price)}</span>
                      <span className="text-[10px] text-gray-400 line-through">{formatPrice(p.price)}</span>
                    </>
                  ) : (
                    <span className="text-sm font-black text-[#9F6B3E]">{formatPrice(p.price)}</span>
                  )}
                </div>
                {isOutOfStock ? (
                  <button disabled className="w-full py-1.5 text-[11px] font-bold text-gray-400 bg-gray-100 rounded-full cursor-not-allowed">
                    售完
                  </button>
                ) : (
                  <button
                    onClick={(e) => handleAdd(p, e)}
                    className="w-full py-1.5 text-[11px] font-bold text-white bg-[#9F6B3E] rounded-full hover:bg-[#85572F] active:scale-95 transition-all"
                  >
                    {isAdded ? '✓' : inCart ? '+ 再加一件' : '+ 加購'}
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
}
