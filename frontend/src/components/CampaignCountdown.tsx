'use client';

/**
 * Campaign countdown — immersive hero-style section below the main banner.
 * Full-width with campaign banner image, large countdown, product thumbnails.
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
    const t = setInterval(() => {
      const v = calc();
      setR(v);
      if (v.total <= 0) clearInterval(t);
    }, 1000);
    return () => clearInterval(t);
  }, [endAt]);
  return r;
}

export default function CampaignCountdown() {
  const [campaign, setCampaign] = useState<Campaign | null>(null);

  useEffect(() => {
    fetch(`${API_URL}/campaigns`)
      .then((r) => (r.ok ? r.json() : []))
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

  const hasBanner = !!campaign.banner_image;
  const bestPrice = campaign.products.length > 0
    ? Math.min(...campaign.products.map((p) => p.campaign_price ?? p.price))
    : null;

  return (
    <section className="relative overflow-hidden">
      {/* Background: campaign banner or gradient fallback */}
      {hasBanner ? (
        <div className="absolute inset-0">
          <Image
            src={imageUrl(campaign.banner_image!)!}
            alt={campaign.name}
            fill
            sizes="100vw"
            className="object-cover"
            priority
          />
          <div className="absolute inset-0 bg-gradient-to-r from-[#1a1410]/80 via-[#1a1410]/50 to-transparent" />
          <div className="absolute inset-0 bg-gradient-to-t from-[#1a1410]/60 to-transparent" />
        </div>
      ) : (
        <div
          className="absolute inset-0"
          style={{
            background:
              'linear-gradient(135deg, #3d2e22 0%, #6b4424 40%, #9F6B3E 100%)',
          }}
        />
      )}

      {/* Decorative elements */}
      <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden>
        <div className="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-white/[0.04] blur-2xl" />
        <div className="absolute bottom-0 left-[20%] w-60 h-60 rounded-full bg-[#f7c79a]/[0.06] blur-3xl" />
      </div>

      <div className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
        <div className="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-8 lg:gap-12 items-center">
          {/* Left: campaign info */}
          <div>
            {/* Badge */}
            <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 mb-5">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-red-500" />
              </span>
              <span className="text-[10px] font-black tracking-[0.2em] text-white/90">
                LIMITED · 限時活動
              </span>
            </div>

            {/* Title */}
            <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black text-white leading-tight mb-3">
              {campaign.name}
            </h2>

            {/* Description */}
            {campaign.description && (
              <p className="text-sm sm:text-base text-white/60 leading-relaxed mb-6 max-w-lg">
                {campaign.description}
              </p>
            )}

            {/* Countdown — large */}
            <div className="mb-6">
              <div className="text-[10px] font-black tracking-[0.2em] text-white/40 mb-3">
                倒數計時
              </div>
              <div className="flex gap-2 sm:gap-3">
                {[
                  { v: days, l: '天' },
                  { v: hours, l: '時' },
                  { v: minutes, l: '分' },
                  { v: seconds, l: '秒' },
                ].map(({ v, l }, i) => (
                  <div key={l} className="flex items-center gap-2 sm:gap-3">
                    <div className="flex flex-col items-center">
                      <div className="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center">
                        <span className="text-2xl sm:text-3xl font-black text-white tabular-nums">
                          {String(v).padStart(2, '0')}
                        </span>
                      </div>
                      <span className="text-[10px] font-bold text-white/40 mt-1.5">
                        {l}
                      </span>
                    </div>
                    {i < 3 && (
                      <span className="text-xl font-black text-white/20 -mt-4">
                        :
                      </span>
                    )}
                  </div>
                ))}
              </div>
            </div>

            {/* CTA + price hint */}
            <div className="flex flex-wrap items-center gap-4">
              <Link
                href={`/campaigns/${campaign.slug}`}
                className="inline-flex items-center gap-2 px-7 py-3.5 bg-white text-[#9F6B3E] font-black rounded-full shadow-xl shadow-black/20 hover:bg-[#fdf7ef] hover:shadow-2xl hover:scale-[1.02] active:scale-[0.98] transition-all duration-300 min-h-[48px]"
              >
                <Icons.Fire className="w-4 h-4" />
                查看活動
              </Link>
              {bestPrice && (
                <div className="text-white/70 text-sm">
                  <span className="text-white/40">最低</span>{' '}
                  <span className="text-xl font-black text-[#fcd561]">
                    ${bestPrice.toLocaleString()}
                  </span>
                  <span className="text-white/40"> 起</span>
                </div>
              )}
            </div>
          </div>

          {/* Right: product cards stack */}
          {campaign.products.length > 0 && (
            <div className="flex gap-3 overflow-x-auto scrollbar-hide pb-2 lg:grid lg:grid-cols-2 lg:gap-3 lg:overflow-visible lg:max-w-[380px]">
              {campaign.products.slice(0, 4).map((p, i) => (
                <Link
                  key={p.id}
                  href={`/products/${p.slug}`}
                  className="shrink-0 w-[140px] sm:w-[160px] lg:w-auto bg-white/10 backdrop-blur-sm rounded-2xl border border-white/15 overflow-hidden hover:bg-white/20 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 group"
                  style={{ animationDelay: `${i * 80}ms` }}
                >
                  <div className="relative aspect-square overflow-hidden">
                    {p.image ? (
                      <Image
                        src={imageUrl(p.image)!}
                        alt={p.name}
                        fill
                        sizes="(max-width: 1024px) 160px, 180px"
                        className="object-cover transition-transform duration-500 group-hover:scale-110"
                      />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center bg-white/5">
                        <Icons.ShoppingBag className="w-8 h-8 text-white/20" />
                      </div>
                    )}
                    {/* Savings badge */}
                    {p.price > (p.campaign_price ?? p.price) && (
                      <span className="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-red-500/90 text-white text-[9px] font-black shadow-sm">
                        省 $
                        {(
                          p.price - (p.campaign_price ?? p.price)
                        ).toLocaleString()}
                      </span>
                    )}
                  </div>
                  <div className="p-2.5">
                    <div className="text-[11px] font-bold text-white/80 line-clamp-1">
                      {p.name}
                    </div>
                    <div className="mt-1 flex items-baseline gap-1.5">
                      <span className="text-sm font-black text-[#fcd561]">
                        ${(p.campaign_price ?? p.price).toLocaleString()}
                      </span>
                      {p.price > (p.campaign_price ?? p.price) && (
                        <span className="text-[10px] text-white/30 line-through">
                          ${p.price.toLocaleString()}
                        </span>
                      )}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
