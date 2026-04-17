'use client';

/**
 * Campaign countdown — shows below hero banner when active campaign exists.
 * Redesigned: clean, brand-consistent, not a standalone banner but
 * an integrated section with product thumbnails + countdown.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { imageUrl } from '@/lib/api';
import Icons from './SvgIcons';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface CampaignProduct {
  id: number;
  name: string;
  slug: string;
  image: string | null;
  price: number;
  campaign_price: number | null;
}

interface Campaign {
  id: number;
  name: string;
  slug: string;
  description: string;
  banner_image: string | null;
  end_at: string;
  is_running: boolean;
  products: CampaignProduct[];
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
    const t = setInterval(() => { const v = calc(); setR(v); if (v.total <= 0) clearInterval(t); }, 1000);
    return () => clearInterval(t);
  }, [endAt]);
  return r;
}

export default function CampaignCountdown() {
  const [campaign, setCampaign] = useState<Campaign | null>(null);

  useEffect(() => {
    fetch(`${API_URL}/campaigns`)
      .then((r) => r.ok ? r.json() : [])
      .then((list: Campaign[]) => {
        const running = list.find((c) => c.is_running);
        if (running) setCampaign(running);
      })
      .catch(() => {});
  }, []);

  if (!campaign) return null;

  return <CountdownSection campaign={campaign} />;
}

function CountdownSection({ campaign }: { campaign: Campaign }) {
  const { total, days, hours, minutes, seconds } = useCountdown(campaign.end_at);
  if (total <= 0) return null;

  return (
    <section className="relative bg-gradient-to-r from-[#fdf7ef] via-white to-[#fdf7ef] border-b border-[#e7d9cb]/50">
      <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-6 sm:py-8">
        {/* Top: campaign name + countdown */}
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 mb-5">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-[#9F6B3E] to-[#85572F] flex items-center justify-center shrink-0">
              <Icons.Fire className="w-5 h-5 text-white" />
            </div>
            <div>
              <div className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E]/60">LIMITED EVENT</div>
              <h3 className="text-lg sm:text-xl font-black text-[#3d2e22]">{campaign.name}</h3>
            </div>
          </div>

          {/* Countdown */}
          <div className="flex items-center gap-1.5 sm:gap-2">
            {[
              { v: days, l: '天' },
              { v: hours, l: '時' },
              { v: minutes, l: '分' },
              { v: seconds, l: '秒' },
            ].map(({ v, l }) => (
              <div key={l} className="flex flex-col items-center">
                <div className="w-11 h-11 sm:w-13 sm:h-13 rounded-xl bg-[#3d2e22] flex items-center justify-center">
                  <span className="text-lg sm:text-xl font-black text-white tabular-nums">
                    {String(v).padStart(2, '0')}
                  </span>
                </div>
                <span className="text-[9px] font-bold text-gray-400 mt-1">{l}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Products row */}
        {campaign.products.length > 0 && (
          <div className="flex gap-3 sm:gap-4 overflow-x-auto scrollbar-hide pb-1">
            {campaign.products.map((p) => (
              <Link
                key={p.id}
                href={`/products/${p.slug}`}
                className="shrink-0 w-[140px] sm:w-[180px] bg-white rounded-2xl border border-[#e7d9cb] overflow-hidden hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group"
              >
                <div className="relative aspect-square bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] overflow-hidden">
                  {p.image ? (
                    <Image
                      src={imageUrl(p.image)!}
                      alt={p.name}
                      fill
                      sizes="180px"
                      className="object-cover transition-transform duration-500 group-hover:scale-110"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center">
                      <Icons.ShoppingBag className="w-8 h-8 text-[#9F6B3E]/20" />
                    </div>
                  )}
                  {/* VIP badge */}
                  <span className="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-[#9F6B3E] text-white text-[9px] font-black">
                    VIP 價
                  </span>
                </div>
                <div className="p-2.5">
                  <div className="text-xs font-bold text-gray-800 line-clamp-1">{p.name}</div>
                  <div className="mt-1 flex items-baseline gap-1.5">
                    <span className="text-sm font-black text-[#9F6B3E]">
                      ${(p.campaign_price ?? p.price).toLocaleString()}
                    </span>
                    {p.price > (p.campaign_price ?? p.price) && (
                      <span className="text-[10px] text-gray-400 line-through">
                        ${p.price.toLocaleString()}
                      </span>
                    )}
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}

        {/* Hint */}
        <div className="mt-4 flex items-center justify-center gap-2 text-[11px] text-[#9F6B3E]/70 font-bold">
          <Icons.Diamond className="w-3.5 h-3.5" />
          <span>活動商品加入購物車，全車享 VIP 優惠價</span>
        </div>
      </div>
    </section>
  );
}
