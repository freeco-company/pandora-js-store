'use client';

/**
 * Bundle card — surfaces a campaign as a buy/gift "套組" unit, with a single
 * add-to-cart action. Used on /campaigns/[slug] and also by any homepage
 * promo surface that wants to render the active bundle.
 */

import { useState, useEffect } from 'react';
import Link from 'next/link';
import type { CampaignBundle } from '@/lib/api';
import { imageUrl } from '@/lib/api';
import { formatPrice } from '@/lib/format';
import { useCart } from '@/components/CartProvider';
import { useToast } from '@/components/Toast';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import SiteIcon from './SiteIcon';

function useCountdown(targetDate: string) {
  const calc = () => {
    const diff = Math.max(0, new Date(targetDate).getTime() - Date.now());
    return {
      total: diff,
      days: Math.floor(diff / 86400000),
      hours: Math.floor((diff % 86400000) / 3600000),
      minutes: Math.floor((diff % 3600000) / 60000),
      seconds: Math.floor((diff % 60000) / 1000),
    };
  };
  const [r, setR] = useState(calc);
  useEffect(() => {
    const t = setInterval(() => {
      const v = calc();
      setR(v);
      if (v.total <= 0) clearInterval(t);
    }, 1000);
    return () => clearInterval(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [targetDate]);
  return r;
}

export default function CampaignBundleCard({ bundle }: { bundle: CampaignBundle }) {
  const { addBundle } = useCart();
  const { toast } = useToast();
  // Countdown uses the parent campaign's end_at (bundles inherit the
  // campaign's time window — they appear/disappear together).
  const endAt = bundle.campaign?.end_at ?? '';
  const { total, days, hours, minutes, seconds } = useCountdown(endAt);
  const [adding, setAdding] = useState(false);

  const savings = bundle.bundle_value_price - bundle.bundle_price;
  const pct = savings > 0 ? Math.round((savings / bundle.bundle_value_price) * 100) : 0;

  const handleAdd = () => {
    setAdding(true);
    addBundle(bundle, 1);
    toast(`已加入：${bundle.name}`);
    setTimeout(() => setAdding(false), 600);
  };

  return (
    <div className="rounded-3xl overflow-hidden border border-[#e7d9cb] bg-white shadow-lg shadow-[#9F6B3E]/5">
      {/* Badge bar */}
      <div className="bg-gradient-to-r from-[#9F6B3E] to-[#85572F] text-white px-5 py-3 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <span className="relative flex h-2 w-2">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
            <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
          </span>
          <span className="text-xs font-black tracking-wider">活動限時優惠</span>
        </div>
        {pct > 0 && (
          <span className="px-2 py-0.5 rounded-full bg-red-500 text-white text-[10px] font-black">
            省 {pct}%
          </span>
        )}
      </div>

      <div className="p-5 sm:p-6">
        {/* Title */}
        <h2 className="text-xl sm:text-2xl font-black text-[#3d2e22] mb-2">{bundle.name}</h2>
        {bundle.description && (
          <p className="text-sm text-[#7a5836]/70 mb-4">{bundle.description}</p>
        )}

        {/* Buy block */}
        <section className="mb-4">
          <div className="flex items-center gap-2 text-xs font-black text-[#9F6B3E] mb-3">
            <span className="w-6 h-6 rounded-md bg-[#9F6B3E] text-white flex items-center justify-center text-[11px]">買</span>
            購買內容
          </div>
          <ul className="space-y-2">
            {bundle.buy_items.map((item, idx) => (
              <li key={`buy-${idx}`} className="flex items-center gap-3 p-3 rounded-xl bg-[#fdf7ef] border border-[#e7d9cb]/50">
                <div className="relative w-12 h-12 rounded-lg overflow-hidden bg-white shrink-0 border border-[#e7d9cb]/50">
                  {item.product.image ? (
                    <ImageWithFallback src={imageUrl(item.product.image)!} alt={item.product.name} fill sizes="48px" className="object-cover" />
                  ) : (
                    <LogoPlaceholder />
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-black text-[#3d2e22] line-clamp-1">{item.product.name}</div>
                  <div className="text-[11px] text-[#7a5836]/70">
                    VIP 價 {formatPrice(item.product.vip_price ?? item.product.price)} × {item.quantity}
                  </div>
                </div>
                <div className="text-xs font-black text-[#9F6B3E]">× {item.quantity}</div>
              </li>
            ))}
          </ul>
        </section>

        {/* Gift block — 商品 gift + 自訂 gift 合併顯示 */}
        {(bundle.gift_items.length > 0 || bundle.custom_gifts.length > 0) && (
          <section className="mb-4">
            <div className="flex items-center gap-2 text-xs font-black text-[#e74c3c] mb-3">
              <span className="w-6 h-6 rounded-md bg-[#e74c3c] text-white flex items-center justify-center text-[11px]">送</span>
              加贈好禮
            </div>
            <ul className="space-y-2">
              {bundle.gift_items.map((item, idx) => (
                <li key={`gift-${idx}`} className="flex items-center gap-3 p-3 rounded-xl bg-[#fef5f3] border border-[#e74c3c]/10">
                  <div className="relative w-12 h-12 rounded-lg overflow-hidden bg-white shrink-0 border border-[#e74c3c]/15">
                    {item.product.image ? (
                      <ImageWithFallback src={imageUrl(item.product.image)!} alt={item.product.name} fill sizes="48px" className="object-cover" />
                    ) : (
                      <LogoPlaceholder />
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-black text-[#3d2e22] line-clamp-1">{item.product.name}</div>
                    <div className="text-[11px] text-[#e74c3c]/70">免費贈送</div>
                  </div>
                  <span className="text-[10px] font-black text-white bg-[#e74c3c] px-2 py-0.5 rounded-full">
                    × {item.quantity} FREE
                  </span>
                </li>
              ))}
              {/* 自訂加贈 — 視覺上跟商品 gift 一致，沒圖就用禮物 icon 佔位 */}
              {bundle.custom_gifts.map((cg, idx) => (
                <li key={`cg-${idx}`} className="flex items-center gap-3 p-3 rounded-xl bg-[#fef5f3] border border-[#e74c3c]/10">
                  <div className="relative w-12 h-12 rounded-lg bg-white shrink-0 border border-[#e74c3c]/15 flex items-center justify-center">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" className="text-[#e74c3c]/60">
                      <path d="M20 12v10H4V12M2 7h20v5H2V7zM12 22V7M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-black text-[#3d2e22] line-clamp-1">{cg.name}</div>
                    <div className="text-[11px] text-[#e74c3c]/70">免費贈送</div>
                  </div>
                  <span className="text-[10px] font-black text-white bg-[#e74c3c] px-2 py-0.5 rounded-full">
                    × {cg.quantity} FREE
                  </span>
                </li>
              ))}
            </ul>
          </section>
        )}

        {/* Price + countdown */}
        <div className="mt-6 p-4 rounded-2xl bg-gradient-to-br from-[#fdf7ef] via-[#f7eee3] to-[#ecd1b3] border border-[#e7d9cb]">
          <div className="flex items-baseline justify-between mb-3">
            <div>
              <div className="text-[10px] font-black text-[#9F6B3E]/60 tracking-wider">套組價</div>
              <div className="flex items-baseline gap-2 flex-wrap">
                <span className="text-3xl sm:text-4xl font-black text-[#c0392b] leading-none">
                  {formatPrice(bundle.bundle_price)}
                </span>
                {savings > 0 && (
                  <span className="inline-flex items-baseline gap-1 text-sm text-gray-400">
                    <span className="text-[10px] font-bold">價值</span>
                    <span className="line-through">{formatPrice(bundle.bundle_value_price)}</span>
                  </span>
                )}
              </div>
              {savings > 0 && (
                <div className="mt-1.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#c0392b]/10 border border-[#c0392b]/15">
                  <span className="text-[11px] font-black text-[#c0392b]">現省 {formatPrice(savings)}・{pct}% OFF</span>
                </div>
              )}
            </div>
          </div>

          {total > 0 && (
            <div className="mb-3">
              <div className="text-[10px] font-black text-[#9F6B3E]/50 tracking-wider mb-1.5">活動倒數</div>
              <div className="flex gap-1.5">
                {[
                  { v: days, l: '天' },
                  { v: hours, l: '時' },
                  { v: minutes, l: '分' },
                  { v: seconds, l: '秒' },
                ].map(({ v, l }, i) => (
                  <div key={l} className="flex flex-col items-center">
                    <div className={`w-11 h-12 rounded-xl flex items-center justify-center relative overflow-hidden ${i === 3 ? 'bg-[#c0392b] text-white' : 'bg-white border border-[#e7d9cb] text-[#3d2e22]'}`}>
                      <span className="text-lg font-black tabular-nums">{String(v).padStart(2, '0')}</span>
                    </div>
                    <span className="text-[8px] font-bold mt-1 text-[#9F6B3E]/50">{l}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          <button
            type="button"
            onClick={handleAdd}
            disabled={adding || total <= 0}
            className="w-full py-3 rounded-full bg-gradient-to-br from-[#c0392b] to-[#a52e22] text-white font-black shadow-md shadow-[#c0392b]/30 active:scale-[0.98] transition-transform disabled:opacity-50 inline-flex items-center justify-center gap-2"
          >
            <SiteIcon name="cart" size={16} />
            {total <= 0 ? '活動已結束' : adding ? '加入中…' : '加入購物車 — 整車升 VIP'}
          </button>

          <p className="mt-3 text-[11px] text-center text-[#7a5836]/70">
            活動限時優惠加入購物車後，其他商品將自動以 VIP 價計算。
            <Link href="/cart" className="text-[#9F6B3E] underline ml-1">前往購物車 →</Link>
          </p>
        </div>
      </div>
    </div>
  );
}
