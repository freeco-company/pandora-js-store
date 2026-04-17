'use client';

/**
 * Campaign deal section for product detail page.
 * Warm, brand-consistent style with flower badge discount.
 * Replaces 3-tier pricing for campaign products.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { formatPrice } from '@/lib/format';

interface CampaignPricingProps {
  campaign: {
    id: number;
    name: string;
    slug: string;
    description: string;
    start_at: string;
    end_at: string;
    is_running: boolean;
    campaign_price: number;
  };
  originalPrice: number;
  shortDescription?: string;
}

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
  }, [targetDate]);
  return r;
}

function parseBundleContents(text: string) {
  const buyMatch = text.match(/買[：:]\s*(.+?)(?:\s*[｜|]\s*送|$)/);
  const giftMatch = text.match(/送[：:]\s*(.+)/);
  const splitItems = (s: string) =>
    s.split(/\s*[+＋]\s*/).map((i) => i.trim()).filter(Boolean);
  return {
    buy: buyMatch ? splitItems(buyMatch[1]) : [],
    gift: giftMatch ? splitItems(giftMatch[1]) : [],
  };
}

/** Cloud-shaped discount badge — same as homepage */
function CloudBadge({ pct }: { pct: number }) {
  return (
    <div className="relative w-[84px] h-[64px] flex items-center justify-center shrink-0" style={{ animation: 'detail-badge-float 4s ease-in-out infinite' }}>
      <svg className="absolute inset-0 w-full h-full drop-shadow-lg" viewBox="0 0 120 90" fill="none">
        <path d="M30 70 C10 70 0 58 4 46 C0 36 8 24 22 22 C24 10 36 2 50 4 C58 -2 72 -2 82 6 C90 2 104 8 108 20 C118 26 120 42 112 52 C118 62 112 74 98 76 C94 84 82 90 70 86 C62 92 48 90 42 82 C34 86 20 80 30 70Z" fill="#c0392b" />
      </svg>
      <div className="relative text-center leading-none -mt-0.5">
        <div className="text-white font-black text-2xl" style={{ textShadow: '0 1px 4px rgba(0,0,0,0.15)' }}>{pct}</div>
        <div className="text-white/80 font-black text-[8px] tracking-wider -mt-0.5">%OFF</div>
      </div>
    </div>
  );
}

export default function CampaignPricing({
  campaign,
  originalPrice,
  shortDescription,
}: CampaignPricingProps) {
  const savings = originalPrice - campaign.campaign_price;
  const pct = savings > 0 ? Math.round((savings / originalPrice) * 100) : 0;
  const { total, days, hours, minutes, seconds } = useCountdown(campaign.end_at);

  if (!campaign.is_running || total <= 0) return null;

  const bundle = shortDescription ? parseBundleContents(shortDescription) : null;

  return (
    <div className="mb-6 space-y-3">
      <style>{`
        @keyframes detail-badge-float {
          0%, 100% { transform: rotate(-4deg) scale(1); }
          50% { transform: rotate(4deg) scale(1.06); }
        }
        @keyframes detail-tick {
          0% { transform: scale(1); }
          15% { transform: scale(1.06); }
          30% { transform: scale(1); }
          100% { transform: scale(1); }
        }
      `}</style>

      {/* ── Campaign card — warm style ── */}
      <div className="rounded-2xl overflow-hidden border border-[#e7d9cb] bg-gradient-to-br from-[#fdf7ef] via-[#f7eee3] to-[#ecd1b3] relative">
        {/* Decorative blobs */}
        <div className="absolute -top-10 -right-10 w-32 h-32 rounded-full bg-[#9F6B3E]/[0.05]" />
        <div className="absolute -bottom-8 -left-8 w-28 h-28 rounded-full bg-[#E0748C]/[0.04]" />

        <div className="relative p-5">
          {/* Top: badge + link */}
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/70 border border-[#e7d9cb]">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#c0392b] opacity-60" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-[#c0392b]" />
              </span>
              <span className="text-[10px] font-black tracking-[0.15em] text-[#9F6B3E]">限時活動</span>
            </div>
            <Link
              href={`/campaigns/${campaign.slug}`}
              className="text-[11px] font-bold text-[#9F6B3E]/60 hover:text-[#9F6B3E] transition-colors"
            >
              查看活動 →
            </Link>
          </div>

          {/* Campaign name + description */}
          <h3 className="text-xl sm:text-2xl font-black text-[#3d2e22] mb-1">{campaign.name}</h3>
          {campaign.description && (
            <p className="text-xs font-bold text-[#7a5836]/70 mb-4">{campaign.description}</p>
          )}

          {/* ── Price + flower badge ── */}
          <div className="flex items-center gap-4 mb-4">
            <div className="flex-1">
              <div className="text-[10px] font-black text-[#9F6B3E]/50 mb-1 tracking-wider">限時優惠價</div>
              <div className="flex items-baseline gap-2 flex-wrap">
                <span className="text-4xl sm:text-5xl font-black text-[#c0392b] leading-none">
                  {formatPrice(campaign.campaign_price)}
                </span>
                {savings > 0 && (
                  <span className="text-base text-gray-400 line-through">
                    {formatPrice(originalPrice)}
                  </span>
                )}
              </div>
              {savings > 0 && (
                <div className="mt-1.5 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#c0392b]/10 border border-[#c0392b]/15">
                  <span className="text-xs font-black text-[#c0392b]">
                    現省 {formatPrice(savings)}
                  </span>
                </div>
              )}
            </div>
            {pct > 0 && <CloudBadge pct={pct} />}
          </div>

          {/* ── Countdown ── */}
          <div>
            <div className="text-[10px] font-black text-[#9F6B3E]/40 tracking-wider mb-2">活動倒數</div>
            <div className="flex gap-1.5">
              {[
                { v: days, l: '天', last: false },
                { v: hours, l: '時', last: false },
                { v: minutes, l: '分', last: false },
                { v: seconds, l: '秒', last: true },
              ].map(({ v, l, last }) => (
                <div key={l} className="flex flex-col items-center">
                  <div
                    className={`w-12 h-13 rounded-xl flex items-center justify-center relative overflow-hidden ${
                      last
                        ? 'bg-[#c0392b] shadow-md shadow-[#c0392b]/20'
                        : 'bg-white shadow-sm border border-[#e7d9cb]'
                    }`}
                    style={last ? { animation: 'detail-tick 1s ease-in-out infinite' } : undefined}
                  >
                    {!last && <div className="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-white to-[#fdf7ef]" />}
                    <span className={`relative text-xl font-black tabular-nums ${last ? 'text-white' : 'text-[#3d2e22]'}`}>
                      {String(v).padStart(2, '0')}
                    </span>
                  </div>
                  <span className={`text-[8px] font-bold mt-1 ${last ? 'text-[#c0392b]/50' : 'text-[#9F6B3E]/40'}`}>{l}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* ── Bundle contents ── */}
      {bundle && (bundle.buy.length > 0 || bundle.gift.length > 0) && (
        <div className="rounded-2xl border border-[#e7d9cb] bg-white p-5">
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-3">
            組合內容
          </div>

          {bundle.buy.length > 0 && (
            <div className="mb-3">
              <div className="text-xs font-black text-[#9F6B3E] mb-2 flex items-center gap-1.5">
                <span className="w-5 h-5 rounded-md bg-[#9F6B3E] text-white flex items-center justify-center text-[10px]">買</span>
                購買商品
              </div>
              <div className="space-y-1.5">
                {bundle.buy.map((item, i) => (
                  <div key={i} className="flex items-center gap-2 text-sm text-gray-700 bg-[#fdf7ef] rounded-xl px-3 py-2 border border-[#e7d9cb]/50">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] shrink-0" />
                    {item}
                  </div>
                ))}
              </div>
            </div>
          )}

          {bundle.gift.length > 0 && (
            <div>
              <div className="text-xs font-black text-[#e74c3c] mb-2 flex items-center gap-1.5">
                <span className="w-5 h-5 rounded-md bg-[#e74c3c] text-white flex items-center justify-center text-[10px]">送</span>
                加贈好禮
              </div>
              <div className="space-y-1.5">
                {bundle.gift.map((item, i) => (
                  <div key={i} className="flex items-center gap-2 text-sm text-gray-700 bg-[#fef5f3] rounded-xl px-3 py-2 border border-[#e74c3c]/10">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#e74c3c] shrink-0" />
                    {item}
                    <span className="ml-auto text-[10px] font-black text-[#e74c3c] bg-[#e74c3c]/10 px-2 py-0.5 rounded-full">FREE</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
