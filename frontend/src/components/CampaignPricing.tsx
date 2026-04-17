'use client';

/**
 * Campaign deal section for product detail page.
 * Completely replaces 3-tier pricing for campaign products.
 * Shows: campaign banner, deal price, bundle contents, countdown.
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
  /** e.g. "買：纖纖飲2盒 + 纖飄錠1盒｜送：爆纖錠1盒 + ..." */
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

/** Parse "買：X｜送：Y" format into buy/gift arrays */
function parseBundleContents(text: string) {
  const buyMatch = text.match(/買[：:]\s*(.+?)(?:\s*[｜|]\s*送|$)/);
  const giftMatch = text.match(/送[：:]\s*(.+)/);

  const splitItems = (s: string) =>
    s
      .split(/\s*[+＋]\s*/)
      .map((i) => i.trim())
      .filter(Boolean);

  return {
    buy: buyMatch ? splitItems(buyMatch[1]) : [],
    gift: giftMatch ? splitItems(giftMatch[1]) : [],
  };
}

export default function CampaignPricing({
  campaign,
  originalPrice,
  shortDescription,
}: CampaignPricingProps) {
  const savings = originalPrice - campaign.campaign_price;
  const pct = savings > 0 ? Math.round((savings / originalPrice) * 100) : 0;

  const { total, days, hours, minutes, seconds } = useCountdown(campaign.end_at);

  // Only show during active campaign period
  if (!campaign.is_running || total <= 0) return null;

  // Parse bundle contents from short_description
  const bundle = shortDescription ? parseBundleContents(shortDescription) : null;

  return (
    <div className="mb-6 space-y-4">
      {/* ── Campaign header card ── */}
      <div className="rounded-2xl overflow-hidden bg-gradient-to-br from-[#3d2e22] via-[#5a3f2b] to-[#9F6B3E] text-white relative">
        {/* Decorative */}
        <div className="absolute -top-12 -right-12 w-40 h-40 rounded-full bg-white/[0.04]" />
        <div className="absolute bottom-0 left-0 w-full h-16 bg-gradient-to-t from-black/20 to-transparent" />

        <div className="relative p-5 sm:p-6">
          {/* Top row: badge + campaign name */}
          <div className="flex items-center gap-3 mb-4">
            <div className="flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/20">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full rounded-full opacity-75 bg-red-400 animate-ping" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
              </span>
              <span className="text-[10px] font-black tracking-[0.15em]">
                限時活動
              </span>
            </div>
            <Link
              href={`/campaigns/${campaign.slug}`}
              className="text-[11px] text-white/50 hover:text-white/80 transition-colors ml-auto"
            >
              查看活動 →
            </Link>
          </div>

          <h3 className="text-xl sm:text-2xl font-black mb-1">{campaign.name}</h3>
          {campaign.description && (
            <p className="text-xs text-white/40 mb-5">{campaign.description}</p>
          )}

          {/* ── Price block ── */}
          <div className="flex items-end gap-3 mb-5">
            <div>
              <div className="text-[10px] text-white/40 mb-1">
                限時優惠價
              </div>
              <span className="text-4xl sm:text-5xl font-black text-[#fcd561] leading-none">
                {formatPrice(campaign.campaign_price)}
              </span>
            </div>
            {savings > 0 && (
              <div className="flex flex-col gap-0.5 pb-1">
                <span className="text-sm text-white/30 line-through">
                  {formatPrice(originalPrice)}
                </span>
                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-500/80 text-[10px] font-black">
                  -{pct}% 省 {formatPrice(savings)}
                </span>
              </div>
            )}
          </div>

          {/* ── Countdown ── */}
          <div>
            <div className="text-[10px] font-black text-white/40 tracking-wider mb-2">
              活動倒數
            </div>
            <div className="flex gap-2">
              {[
                { v: days, l: '天' },
                { v: hours, l: '時' },
                { v: minutes, l: '分' },
                { v: seconds, l: '秒' },
              ].map(({ v, l }) => (
                <div key={l} className="flex flex-col items-center">
                  <div className="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-white/10 backdrop-blur-sm border border-white/10 flex items-center justify-center">
                    <span className="text-xl sm:text-2xl font-black tabular-nums">
                      {String(v).padStart(2, '0')}
                    </span>
                  </div>
                  <span className="text-[9px] text-white/30 mt-1">{l}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* ── Bundle contents (parsed from short_description) ── */}
      {bundle && (bundle.buy.length > 0 || bundle.gift.length > 0) && (
        <div className="rounded-2xl border border-[#e7d9cb] bg-[#fdf7ef] p-5">
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
                  <div
                    key={i}
                    className="flex items-center gap-2 text-sm text-gray-700 bg-white rounded-xl px-3 py-2 border border-[#e7d9cb]/50"
                  >
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
                  <div
                    key={i}
                    className="flex items-center gap-2 text-sm text-gray-700 bg-white rounded-xl px-3 py-2 border border-[#e74c3c]/15"
                  >
                    <span className="w-1.5 h-1.5 rounded-full bg-[#e74c3c] shrink-0" />
                    {item}
                    <span className="ml-auto text-[10px] font-black text-[#e74c3c] bg-[#e74c3c]/10 px-2 py-0.5 rounded-full">
                      FREE
                    </span>
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
