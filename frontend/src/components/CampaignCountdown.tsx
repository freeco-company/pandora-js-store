'use client';

/**
 * Campaign countdown — sphere-reveal animation.
 * A 3D-ish golden sphere pulses in center, then expands;
 * content cards fly out from behind it and land in place.
 * Only renders when a campaign is_running.
 */

import { useEffect, useRef, useState, useCallback } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { imageUrl } from '@/lib/api';

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
  start_at: string;
  end_at: string;
  is_running: boolean;
  products: CampaignProduct[];
}

function useCountdown(endAt: string) {
  const calc = useCallback(() => {
    const diff = Math.max(0, new Date(endAt).getTime() - Date.now());
    return {
      total: diff,
      days: Math.floor(diff / 86400000),
      hours: Math.floor((diff % 86400000) / 3600000),
      minutes: Math.floor((diff % 3600000) / 60000),
      seconds: Math.floor((diff % 60000) / 1000),
    };
  }, [endAt]);
  const [r, setR] = useState(calc);
  useEffect(() => {
    const t = setInterval(() => {
      const v = calc();
      setR(v);
      if (v.total <= 0) clearInterval(t);
    }, 1000);
    return () => clearInterval(t);
  }, [calc]);
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
  const sectionRef = useRef<HTMLDivElement>(null);
  const [phase, setPhase] = useState<'idle' | 'pulse' | 'burst' | 'done'>('idle');

  useEffect(() => {
    const el = sectionRef.current;
    if (!el) return;
    let timers: ReturnType<typeof setTimeout>[] = [];
    let obs: IntersectionObserver | null = null;

    const startObserving = () => {
      // Check: is the section currently in viewport?
      const rect = el.getBoundingClientRect();
      const inView = rect.top < window.innerHeight && rect.bottom > 0;

      if (inView) {
        // Already visible (e.g. mobile, section near top) — play immediately
        timers.push(setTimeout(() => setPhase('pulse'), 100));
        timers.push(setTimeout(() => setPhase('burst'), 1000));
        timers.push(setTimeout(() => setPhase('done'), 2100));
      } else {
        // Not visible yet — watch for scroll
        obs = new IntersectionObserver(
          ([entry]) => {
            if (entry.isIntersecting) {
              timers.push(setTimeout(() => setPhase('pulse'), 150));
              timers.push(setTimeout(() => setPhase('burst'), 1100));
              timers.push(setTimeout(() => setPhase('done'), 2300));
              obs?.disconnect();
            }
          },
          { threshold: 0.1, rootMargin: '-40px 0px' },
        );
        obs.observe(el);
      }
    };

    // Wait for layout to settle after mount before checking position
    const delay = setTimeout(startObserving, 500);

    return () => {
      clearTimeout(delay);
      timers.forEach(clearTimeout);
      obs?.disconnect();
    };
  }, []);

  if (total <= 0) return null;

  const bestPrice =
    campaign.products.length > 0
      ? Math.min(...campaign.products.map((p) => p.campaign_price ?? p.price))
      : null;

  const active = phase !== 'idle';
  const bursting = phase === 'burst' || phase === 'done';
  const settled = phase === 'done';

  return (
    <div className="relative" ref={sectionRef}>
      <section className="relative overflow-hidden">
        {/* ── Top wave — absolute inside section ── */}
        <div className="absolute top-0 left-0 right-0 z-20 overflow-hidden" style={{ height: 48 }}>
          <svg className="absolute bottom-0 left-0 w-[200%] h-full campaign-wave-top" viewBox="0 0 2400 80" preserveAspectRatio="none">
            <path d="M0 45 C200 72 400 18 600 45 C800 72 1000 18 1200 45 C1400 72 1600 18 1800 45 C2000 72 2200 18 2400 45 L2400 80 L0 80Z" fill="#ecd1b3" />
            <path d="M0 55 C200 78 400 32 600 55 C800 78 1000 32 1200 55 C1400 78 1600 32 1800 55 C2000 78 2200 32 2400 55 L2400 80 L0 80Z" fill="#f5e1c8" opacity="0.6" />
          </svg>
        </div>
        {/* ── Background + breathing ripples ── */}
        <div className="absolute inset-0 overflow-hidden" aria-hidden>
          <div className="absolute inset-0" style={{ background: 'linear-gradient(160deg, #f5e1c8 0%, #ecd1b3 30%, #e7c9a8 55%, #f2ddc4 100%)' }} />
          {/* Continuous ripples from center — synced with countdown seconds */}
          {settled && (
            <>
              <div className="campaign-ripple campaign-ripple-1" />
              <div className="campaign-ripple campaign-ripple-2" />
              <div className="campaign-ripple campaign-ripple-3" />
            </>
          )}
        </div>

      <style>{`
        /* Continuous breathing ripples from center */
        .campaign-ripple {
          position: absolute;
          left: 50%; top: 50%;
          border-radius: 50%;
          transform: translate(-50%, -50%) scale(0);
          will-change: transform, opacity;
          background: radial-gradient(circle, rgba(159,107,62,0.06) 0%, rgba(159,107,62,0.02) 50%, transparent 70%);
          border: 2px solid rgba(159,107,62,0.15);
        }
        .campaign-ripple-1 {
          width: 250px; height: 250px;
          animation: ripple-breathe 4s ease-out 0s infinite;
        }
        .campaign-ripple-2 {
          width: 250px; height: 250px;
          animation: ripple-breathe 4s ease-out 1.3s infinite;
        }
        .campaign-ripple-3 {
          width: 250px; height: 250px;
          animation: ripple-breathe 4s ease-out 2.6s infinite;
        }
        @keyframes ripple-breathe {
          0% { transform: translate(-50%, -50%) scale(0.2); opacity: 0.7; border-width: 2.5px; }
          100% { transform: translate(-50%, -50%) scale(5); opacity: 0; border-width: 0.5px; }
        }

        /* Waves continuous scroll */
        .campaign-wave-top {
          animation: wave-scroll 8s linear infinite;
        }
        .campaign-wave-bottom {
          animation: wave-scroll 10s linear infinite reverse;
        }
        @keyframes wave-scroll {
          0% { transform: translateX(0); }
          100% { transform: translateX(-50%); }
        }

        @keyframes sphere-in {
          0% { transform: translate(-50%, -50%) scale(0); opacity: 0; }
          60% { transform: translate(-50%, -50%) scale(1.05); opacity: 1; }
          100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        @keyframes sphere-pulse {
          0%, 100% { transform: translate(-50%, -50%) scale(1); }
          50% { transform: translate(-50%, -50%) scale(1.06); }
        }
        @keyframes sphere-expand {
          0% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
          100% { transform: translate(-50%, -50%) scale(8); opacity: 0; }
        }
        @keyframes ring-out {
          0% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
          100% { transform: translate(-50%, -50%) scale(4); opacity: 0; }
        }
        @keyframes card-fly {
          0% { opacity: 0; transform: scale(0.3) translateY(40px); filter: blur(8px); }
          60% { filter: blur(0px); }
          80% { transform: scale(1.03) translateY(-4px); }
          100% { opacity: 1; transform: scale(1) translateY(0); filter: blur(0px); }
        }
        @keyframes glow-breathe {
          0%, 100% { opacity: 0.4; transform: translate(-50%, -50%) scale(1); }
          50% { opacity: 0.6; transform: translate(-50%, -50%) scale(1.05); }
        }
        @keyframes float-tag {
          0%, 100% { transform: translateY(0); }
          50% { transform: translateY(-6px); }
        }
        @keyframes tick-pulse {
          0% { transform: scale(1); box-shadow: 0 4px 20px rgba(192,57,43,0.08); }
          15% { transform: scale(1.04); box-shadow: 0 4px 24px rgba(192,57,43,0.18); }
          30% { transform: scale(1); box-shadow: 0 4px 20px rgba(192,57,43,0.08); }
          100% { transform: scale(1); box-shadow: 0 4px 20px rgba(192,57,43,0.08); }
        }
        @keyframes urgency-ring {
          0% { box-shadow: 0 0 0 0 rgba(192,57,43,0.15); }
          70% { box-shadow: 0 0 0 6px rgba(192,57,43,0); }
          100% { box-shadow: 0 0 0 0 rgba(192,57,43,0); }
        }
      `}</style>

      {/* ── Central sphere ── */}
      <div className="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden>
        {/* Glow behind sphere */}
        {active && (
          <div
            className="absolute left-1/2 top-1/2 w-[350px] h-[350px] rounded-full"
            style={{
              background: 'radial-gradient(circle, rgba(159,107,62,0.2) 0%, transparent 70%)',
              animation: bursting ? 'sphere-expand 1.2s cubic-bezier(0.16,1,0.3,1) forwards' : 'glow-breathe 2s ease-in-out infinite',
            }}
          />
        )}

        {/* The sphere itself */}
        {active && !settled && (
          <div
            className="absolute left-1/2 top-1/2 w-[180px] h-[180px] sm:w-[220px] sm:h-[220px] rounded-full"
            style={{
              background: 'radial-gradient(circle at 35% 35%, #f7dbb8 0%, #d4a574 40%, #9F6B3E 80%, #7a5128 100%)',
              boxShadow: '0 20px 60px rgba(159,107,62,0.3), inset 0 -8px 20px rgba(0,0,0,0.15), inset 0 8px 20px rgba(255,255,255,0.3)',
              animation: bursting
                ? 'sphere-expand 1.2s cubic-bezier(0.16,1,0.3,1) forwards'
                : 'sphere-in 0.8s cubic-bezier(0.16,1,0.3,1) forwards, sphere-pulse 1.5s ease-in-out 0.8s infinite',
            }}
          />
        )}

        {/* Expanding rings on burst */}
        {bursting && (
          <>
            <div
              className="absolute left-1/2 top-1/2 w-[200px] h-[200px] rounded-full border-2 border-[#9F6B3E]/30"
              style={{ animation: 'ring-out 1.5s cubic-bezier(0.16,1,0.3,1) forwards' }}
            />
            <div
              className="absolute left-1/2 top-1/2 w-[200px] h-[200px] rounded-full border border-[#d4a574]/20"
              style={{ animation: 'ring-out 1.8s cubic-bezier(0.16,1,0.3,1) 0.15s forwards' }}
            />
          </>
        )}

        {/* Post-burst: removed, replaced by continuous ripples above */}
      </div>

      {/* ── Content: flies out from center after burst ── */}
      <div
        className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 pt-16 pb-16 sm:pt-18 sm:pb-18 z-10"
        style={{ opacity: bursting ? 1 : 0, pointerEvents: bursting ? 'auto' : 'none' }}
      >
        {/* Title cluster */}
        <div
          className="text-center mb-8"
          style={{
            opacity: 0,
            animation: bursting ? 'card-fly 0.8s cubic-bezier(0.16,1,0.3,1) 0s forwards' : 'none',
          }}
        >
          <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 backdrop-blur border border-[#e7d9cb] shadow-sm mb-4">
            <span className="relative flex h-2 w-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#c0392b] opacity-60" />
              <span className="relative inline-flex rounded-full h-2 w-2 bg-[#c0392b]" />
            </span>
            <span className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E]">限時活動</span>
          </div>
          <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22] tracking-tight mb-2">
            {campaign.name}
          </h2>
          {campaign.description && (
            <p className="text-sm font-bold text-[#7a5836] max-w-md mx-auto">{campaign.description}</p>
          )}
        </div>

        {/* Countdown — urgency feel, seconds tick-pulses */}
        <div
          className="flex justify-center items-center gap-2 sm:gap-3 mb-10 px-5 sm:px-8 py-4 rounded-[2rem] mx-auto w-fit"
          style={{
            background: 'linear-gradient(135deg, rgba(255,255,255,0.7) 0%, rgba(253,247,239,0.6) 100%)',
            backdropFilter: 'blur(8px)',
            border: '1px solid rgba(192,57,43,0.1)',
            animation: settled ? 'urgency-ring 2s ease-in-out infinite' : 'none',
          }}
        >
          {/* "倒數" label */}
          <div
            className="hidden sm:flex flex-col items-center mr-2"
            style={{
              opacity: 0,
              animation: bursting ? 'card-fly 0.7s cubic-bezier(0.16,1,0.3,1) 0.05s forwards' : 'none',
            }}
          >
            <span className="text-[10px] font-black tracking-[0.15em] text-[#c0392b]/70">倒數</span>
          </div>

          {[
            { v: days, l: '天', isLast: false },
            { v: hours, l: '時', isLast: false },
            { v: minutes, l: '分', isLast: false },
            { v: seconds, l: '秒', isLast: true },
          ].map(({ v, l, isLast }, i) => (
            <div key={l} className="flex items-center gap-2 sm:gap-3">
              <div
                className="flex flex-col items-center"
                style={{
                  opacity: 0,
                  animation: bursting ? `card-fly 0.8s cubic-bezier(0.16,1,0.3,1) ${0.08 + i * 0.1}s forwards` : 'none',
                }}
              >
                <div
                  className={`w-14 h-16 sm:w-[72px] sm:h-[84px] rounded-2xl flex items-center justify-center relative overflow-hidden ${
                    isLast
                      ? 'bg-gradient-to-b from-[#c0392b] to-[#a5281b] shadow-lg shadow-[#c0392b]/20'
                      : 'bg-white shadow-lg shadow-[#9F6B3E]/[0.06] border border-[#e7d9cb]'
                  }`}
                  style={isLast && settled ? { animation: 'tick-pulse 1s ease-in-out infinite' } : undefined}
                >
                  {!isLast && (
                    <>
                      <div className="absolute top-0 left-0 right-0 h-1/2 bg-gradient-to-b from-white to-[#fdf7ef]" />
                      <div className="absolute left-1.5 right-1.5 top-[calc(50%-0.5px)] h-px bg-[#e7d9cb]/40" />
                    </>
                  )}
                  <span className={`relative text-2xl sm:text-4xl font-black tabular-nums leading-none ${isLast ? 'text-white' : 'text-[#3d2e22]'}`}>
                    {String(v).padStart(2, '0')}
                  </span>
                </div>
                <span className={`text-[9px] font-bold mt-1.5 ${isLast ? 'text-[#c0392b]/60' : 'text-[#9F6B3E]/40'}`}>{l}</span>
              </div>
              {i < 3 && (
                <div className="flex flex-col items-center gap-1 -mt-4" style={{ opacity: bursting ? 1 : 0, transition: 'opacity 0.5s 0.5s' }}>
                  <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E]/20" />
                  <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E]/20" />
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Products — fly in from center staggered */}
        {campaign.products.length > 0 && (
          <div className="grid grid-cols-2 sm:flex sm:justify-center gap-3 sm:gap-4">
            {campaign.products.slice(0, 4).map((p, i) => {
              const saved = p.price - (p.campaign_price ?? p.price);
              const pct = saved > 0 ? Math.round((saved / p.price) * 100) : 0;
              return (
                <Link
                  key={p.id}
                  href={`/products/${p.slug}`}
                  className="sm:w-[220px] group bg-white rounded-3xl overflow-hidden border border-[#e7d9cb]/60 shadow-md shadow-[#9F6B3E]/[0.04] hover:shadow-xl hover:shadow-[#9F6B3E]/[0.12] hover:-translate-y-2 transition-all duration-500"
                  style={{
                    opacity: 0,
                    animation: bursting ? `card-fly 0.9s cubic-bezier(0.16,1,0.3,1) ${0.15 + i * 0.12}s forwards` : 'none',
                  }}
                >
                  <div className="relative aspect-square overflow-hidden bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3]">
                    {p.image ? (
                      <Image
                        src={imageUrl(p.image)!}
                        alt={p.name}
                        fill
                        sizes="220px"
                        className="object-cover transition-transform duration-700 group-hover:scale-105"
                      />
                    ) : (
                      <div className="absolute inset-0 flex items-center justify-center">
                        <svg className="w-12 h-12 text-[#9F6B3E]/10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                        </svg>
                      </div>
                    )}
                    {pct > 0 && (
                      <div className="absolute top-3 left-3 px-2.5 py-1 rounded-xl bg-[#c0392b] text-white text-[11px] font-black shadow-md shadow-[#c0392b]/30">
                        -{pct}%
                      </div>
                    )}
                  </div>
                  <div className="p-4">
                    <div className="text-sm font-bold text-gray-700 line-clamp-1 mb-2 group-hover:text-[#9F6B3E] transition-colors">
                      {p.name}
                    </div>
                    <div className="flex items-baseline gap-2">
                      <span className="text-xl font-black text-[#9F6B3E]">
                        ${(p.campaign_price ?? p.price).toLocaleString()}
                      </span>
                      {saved > 0 && (
                        <span className="text-xs text-gray-400 line-through">
                          ${p.price.toLocaleString()}
                        </span>
                      )}
                    </div>
                  </div>
                </Link>
              );
            })}
          </div>
        )}

        {/* CTA */}
        <div
          className="mt-8 flex flex-col sm:flex-row items-center justify-center gap-4"
          style={{
            opacity: 0,
            animation: bursting ? 'card-fly 0.8s cubic-bezier(0.16,1,0.3,1) 0.5s forwards' : 'none',
          }}
        >
          <Link
            href={`/campaigns/${campaign.slug}`}
            className="group/btn inline-flex items-center gap-2 px-8 py-3.5 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/25 hover:bg-[#85572F] hover:shadow-xl active:scale-[0.97] transition-all duration-300 min-h-[48px]"
          >
            查看活動詳情
            <span className="transition-transform duration-300 group-hover/btn:translate-x-1">→</span>
          </Link>
          {bestPrice && (
            <div className="flex items-baseline gap-1.5">
              <span className="text-sm font-bold text-[#7a5836]">最低</span>
              <span className="text-2xl font-black text-[#9F6B3E]">${bestPrice.toLocaleString()}</span>
              <span className="text-sm font-bold text-[#7a5836]">起</span>
            </div>
          )}
        </div>
      </div>

      {/* ── Floating branded mini-cards (post-settle) ── */}
      {settled && (
        <div className="absolute inset-0 pointer-events-none z-0 hidden lg:block" aria-hidden>
          {[
            { l: '3%', t: '15%', icon: '🛡️', text: '官方正品', bg: 'bg-white', delay: 0 },
            { l: '88%', t: '12%', icon: '⏰', text: '限量搶購', bg: 'bg-white', delay: 0.8 },
            { l: '2%', t: '72%', icon: '🎀', text: '搭配更划算', bg: 'bg-white', delay: 0.4 },
            { l: '85%', t: '78%', icon: '💝', text: '母親節限定', bg: 'bg-white', delay: 1.2 },
            { l: '15%', t: '90%', icon: '🚚', text: '快速出貨', bg: 'bg-white', delay: 1.6 },
            { l: '75%', t: '5%', icon: '✨', text: 'VIP 優惠', bg: 'bg-white', delay: 0.6 },
          ].map((tag, i) => (
            <div
              key={i}
              className={`absolute ${tag.bg} rounded-2xl shadow-md shadow-[#9F6B3E]/[0.06] border border-[#e7d9cb]/60 px-3 py-2 flex items-center gap-2 opacity-0`}
              style={{
                left: tag.l,
                top: tag.t,
                animation: `card-fly 0.7s cubic-bezier(0.16,1,0.3,1) ${tag.delay}s forwards, float-tag ${3.5 + i * 0.3}s ease-in-out ${tag.delay + 0.7}s infinite`,
              }}
            >
              <span className="text-base">{tag.icon}</span>
              <span className="text-[10px] font-black text-[#7a5836] whitespace-nowrap">{tag.text}</span>
            </div>
          ))}
        </div>
      )}

        {/* ── Bottom wave — absolute inside section ── */}
        <div className="absolute bottom-0 left-0 right-0 z-20 overflow-hidden" style={{ height: 48 }}>
          <svg className="absolute top-0 left-0 w-[200%] h-full campaign-wave-bottom" viewBox="0 0 2400 80" preserveAspectRatio="none">
            <path d="M0 35 C200 8 400 62 600 35 C800 8 1000 62 1200 35 C1400 8 1600 62 1800 35 C2000 8 2200 62 2400 35 L2400 0 L0 0Z" fill="#ecd1b3" />
            <path d="M0 25 C200 2 400 48 600 25 C800 2 1000 48 1200 25 C1400 2 1600 48 1800 25 C2000 2 2200 48 2400 25 L2400 0 L0 0Z" fill="#f5e1c8" opacity="0.6" />
          </svg>
        </div>
      </section>
    </div>
  );
}
