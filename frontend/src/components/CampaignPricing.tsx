'use client';

/**
 * Campaign pricing section for product detail page.
 * Replaces the 3-tier pricing when a product is part of an active campaign.
 * Shows campaign name, countdown timer, and campaign-specific price.
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
    end_at: string;
    campaign_price: number;
  };
  originalPrice: number;
}

function useCountdown(endAt: string) {
  const calc = () => {
    const diff = Math.max(0, new Date(endAt).getTime() - Date.now());
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
  }, [endAt]);
  return r;
}

export default function CampaignPricing({ campaign, originalPrice }: CampaignPricingProps) {
  const { total, days, hours, minutes, seconds } = useCountdown(campaign.end_at);
  const savings = originalPrice - campaign.campaign_price;
  const pct = Math.round((savings / originalPrice) * 100);

  if (total <= 0) return null;

  return (
    <div className="mb-6">
      {/* Campaign badge + link */}
      <div className="flex items-center justify-between mb-3">
        <div className="text-[10px] font-black tracking-[0.2em] text-[#c0392b]">EVENT · 限時活動</div>
        <Link href={`/campaigns/${campaign.slug}`} className="text-[10px] text-[#9F6B3E] underline hover:text-[#7a5836]">
          查看活動 →
        </Link>
      </div>

      {/* Main campaign card */}
      <div className="rounded-2xl overflow-hidden border-2 border-[#c0392b]/20 bg-gradient-to-br from-[#fef5f3] to-[#fff0ec]">
        {/* Campaign header */}
        <div className="bg-gradient-to-r from-[#c0392b] to-[#e74c3c] px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="w-2 h-2 rounded-full bg-white animate-pulse" />
            <span className="text-sm font-black text-white">{campaign.name}</span>
          </div>
          {savings > 0 && (
            <span className="px-2 py-0.5 rounded-full bg-white/20 text-[10px] font-black text-white">
              省 {pct}%
            </span>
          )}
        </div>

        <div className="p-4">
          {/* Price comparison */}
          <div className="flex items-baseline gap-3 mb-4">
            <span className="text-3xl font-black text-[#c0392b]">
              {formatPrice(campaign.campaign_price)}
            </span>
            {savings > 0 && (
              <>
                <span className="text-base text-gray-400 line-through">
                  {formatPrice(originalPrice)}
                </span>
                <span className="text-sm font-black text-[#c0392b]">
                  省 {formatPrice(savings)}
                </span>
              </>
            )}
          </div>

          {/* Countdown */}
          <div className="flex items-center gap-2 mb-3">
            <span className="text-[10px] font-black text-gray-500 tracking-wider shrink-0">倒數</span>
            <div className="flex gap-1">
              {[
                { v: days, l: '天' },
                { v: hours, l: '時' },
                { v: minutes, l: '分' },
                { v: seconds, l: '秒' },
              ].map(({ v, l }) => (
                <div key={l} className="flex items-center gap-0.5">
                  <span className="w-8 h-8 rounded-lg bg-[#3d2e22] flex items-center justify-center text-sm font-black text-white tabular-nums">
                    {String(v).padStart(2, '0')}
                  </span>
                  <span className="text-[9px] text-gray-400">{l}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Campaign description */}
          {campaign.description && (
            <p className="text-xs text-gray-500 leading-relaxed">{campaign.description}</p>
          )}
        </div>
      </div>
    </div>
  );
}
