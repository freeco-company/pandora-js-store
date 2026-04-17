'use client';

/**
 * Homepage campaign countdown banner — shows when there's an active campaign.
 * Auto-hides when no campaign is running. Clicking navigates to /campaigns/[slug].
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { imageUrl } from '@/lib/api';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface Campaign {
  id: number;
  name: string;
  slug: string;
  description: string;
  banner_image: string | null;
  end_at: string;
  is_running: boolean;
}

function useCountdown(endAt: string) {
  const [remaining, setRemaining] = useState(() => calcRemaining(endAt));

  useEffect(() => {
    const timer = setInterval(() => {
      const r = calcRemaining(endAt);
      setRemaining(r);
      if (r.total <= 0) clearInterval(timer);
    }, 1000);
    return () => clearInterval(timer);
  }, [endAt]);

  return remaining;
}

function calcRemaining(endAt: string) {
  const diff = Math.max(0, new Date(endAt).getTime() - Date.now());
  return {
    total: diff,
    days: Math.floor(diff / 86400000),
    hours: Math.floor((diff % 86400000) / 3600000),
    minutes: Math.floor((diff % 3600000) / 60000),
    seconds: Math.floor((diff % 60000) / 1000),
  };
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

  return <CountdownBanner campaign={campaign} />;
}

function CountdownBanner({ campaign }: { campaign: Campaign }) {
  const { total, days, hours, minutes, seconds } = useCountdown(campaign.end_at);

  if (total <= 0) return null;

  return (
    <section className="relative overflow-hidden">
      <Link
        href={`/campaigns/${campaign.slug}`}
        className="block group"
      >
        <div className="bg-gradient-to-r from-[#9F6B3E] via-[#c9935a] to-[#9F6B3E] py-6 sm:py-8 px-5 sm:px-6">
          <div className="max-w-[1290px] mx-auto flex flex-col sm:flex-row items-center gap-4 sm:gap-8">
            {/* Left: banner image or emoji */}
            {campaign.banner_image ? (
              <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden shrink-0 shadow-lg">
                <Image
                  src={imageUrl(campaign.banner_image)!}
                  alt={campaign.name}
                  width={96}
                  height={96}
                  className="object-cover w-full h-full"
                />
              </div>
            ) : (
              <div className="text-5xl">🔥</div>
            )}

            {/* Center: title + description */}
            <div className="flex-1 text-center sm:text-left min-w-0">
              <div className="text-[10px] font-black tracking-[0.3em] text-white/70">LIMITED EVENT</div>
              <h2 className="text-xl sm:text-2xl font-black text-white mt-1 truncate">
                {campaign.name}
              </h2>
              {campaign.description && (
                <p className="text-xs text-white/80 mt-1 line-clamp-1">{campaign.description}</p>
              )}
            </div>

            {/* Right: countdown */}
            <div className="flex items-center gap-2 sm:gap-3 shrink-0">
              {[
                { value: days, label: '天' },
                { value: hours, label: '時' },
                { value: minutes, label: '分' },
                { value: seconds, label: '秒' },
              ].map(({ value, label }) => (
                <div key={label} className="flex flex-col items-center">
                  <div className="w-12 sm:w-14 h-12 sm:h-14 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
                    <span className="text-xl sm:text-2xl font-black text-white tabular-nums">
                      {String(value).padStart(2, '0')}
                    </span>
                  </div>
                  <span className="text-[9px] font-bold text-white/60 mt-1">{label}</span>
                </div>
              ))}
            </div>
          </div>

          {/* CTA hint */}
          <div className="text-center mt-4 sm:mt-3">
            <span className="inline-flex items-center gap-1.5 text-xs font-black text-white/90 group-hover:text-white transition-colors">
              查看活動商品 →
            </span>
          </div>
        </div>
      </Link>
    </section>
  );
}
