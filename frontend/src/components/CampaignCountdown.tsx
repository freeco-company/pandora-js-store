'use client';

/**
 * Campaign countdown — sphere-reveal animation.
 * A 3D-ish golden sphere pulses in center, then expands;
 * content cards fly out from behind it and land in place.
 * Only renders when a campaign is_running.
 */

import { useEffect, useRef, useState, useCallback } from 'react';
import Link from 'next/link';
import { API_URL, imageUrl, type Campaign, type CampaignBundle } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import SiteIcon from '@/components/SiteIcon';

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
    campaign.bundles.length > 0
      ? Math.min(...campaign.bundles.map((b) => b.bundle_price))
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

        @keyframes flower-in {
          0% { transform: scale(0) rotate(-20deg); opacity: 0; }
          60% { transform: scale(1.1) rotate(5deg); opacity: 1; }
          100% { transform: scale(1) rotate(0deg); opacity: 1; }
        }
        @keyframes flower-breathe {
          0%, 100% { transform: scale(1) rotate(0deg); }
          50% { transform: scale(1.04) rotate(2deg); }
        }
        @keyframes flower-bloom {
          0% { transform: scale(1) rotate(0deg); opacity: 1; }
          40% { transform: scale(1.5) rotate(15deg); opacity: 0.9; }
          100% { transform: scale(3) rotate(45deg); opacity: 0; filter: blur(3px); }
        }
        @keyframes petal-drift {
          0% { opacity: 1; transform: translate(var(--sx), var(--sy)) rotate(var(--sr)) scale(var(--ps)); }
          70% { opacity: 0.7; }
          100% { opacity: 0; transform: translate(var(--px), calc(var(--py) + 40px)) rotate(var(--pr)) scale(calc(var(--ps)*0.7)); }
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
        @keyframes badge-wobble {
          0%, 100% { transform: rotate(-4deg) scale(1); }
          50% { transform: rotate(4deg) scale(1.06); }
        }
      `}</style>

      {/* ── Central seed → leaf burst ── */}
      <div className="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden>
        {/* Glow behind seed */}
        {active && (
          <div
            className="absolute left-1/2 top-1/2 w-[300px] h-[300px] rounded-full"
            style={{
              background: 'radial-gradient(circle, rgba(122,184,122,0.2) 0%, rgba(159,107,62,0.1) 40%, transparent 70%)',
              animation: bursting ? 'sphere-expand 1.2s cubic-bezier(0.16,1,0.3,1) forwards' : 'glow-breathe 2s ease-in-out infinite',
            }}
          />
        )}

        {/* Flower — multi-layered, rotates in, breathes, then blooms open */}
        {active && !settled && (
          <div
            className="absolute left-1/2 top-1/2"
            style={{
              marginLeft: -70, marginTop: -70,
              animation: bursting
                ? 'flower-bloom 1s cubic-bezier(0.16,1,0.3,1) forwards'
                : 'flower-in 0.8s cubic-bezier(0.16,1,0.3,1) forwards, flower-breathe 2s ease-in-out 0.8s infinite',
            }}
          >
            <svg width="140" height="140" viewBox="0 0 140 140" fill="none">
              {/* Outer petals — warm pink */}
              {[0, 60, 120, 180, 240, 300].map((deg) => (
                <g key={`o${deg}`} transform={`rotate(${deg} 70 70)`}>
                  <ellipse cx="70" cy="26" rx="22" ry="30" fill="#f4a0b5" opacity="0.6" />
                  <ellipse cx="70" cy="30" rx="16" ry="22" fill="#f7b8c8" opacity="0.4" />
                </g>
              ))}
              {/* Middle petals — soft coral */}
              {[30, 90, 150, 210, 270, 330].map((deg) => (
                <g key={`m${deg}`} transform={`rotate(${deg} 70 70)`}>
                  <ellipse cx="70" cy="34" rx="18" ry="24" fill="#e8875a" opacity="0.5" />
                  <ellipse cx="70" cy="37" rx="12" ry="16" fill="#f0a882" opacity="0.4" />
                </g>
              ))}
              {/* Inner petals — brand gold */}
              {[15, 75, 135, 195, 255, 315].map((deg) => (
                <ellipse key={`i${deg}`} cx="70" cy="42" rx="14" ry="18" fill="#d4a574" opacity="0.6" transform={`rotate(${deg} 70 70)`} />
              ))}
              {/* Center */}
              <circle cx="70" cy="70" r="20" fill="#9F6B3E" opacity="0.9" />
              <circle cx="70" cy="70" r="14" fill="#c8835a" />
              <circle cx="70" cy="70" r="8" fill="#f7dbb8" />
              {/* Center dots */}
              {[0, 60, 120, 180, 240, 300].map((deg) => {
                const r = 5;
                const x = 70 + Math.cos((deg * Math.PI) / 180) * r;
                const y = 70 + Math.sin((deg * Math.PI) / 180) * r;
                return <circle key={`d${deg}`} cx={x} cy={y} r="1.5" fill="#9F6B3E" opacity="0.5" />;
              })}
              {/* Highlight */}
              <circle cx="64" cy="64" r="4" fill="white" opacity="0.25" />
            </svg>
          </div>
        )}

        {/* Petals drifting — large, few, natural float like cherry blossoms */}
        {bursting && (() => {
          const petals: { sx: number; sy: number; sr: number; x: number; y: number; size: number; rot: number; color: string; delay: number; scale: number; dur: number }[] = [];
          const colors = ['#f4a0b5', '#f7b8c8', '#fce4ec', '#e8875a', '#f0a882', '#d4a574', '#f7dbb8'];
          const startRadius = 60; // start from a circle, not center point
          for (let i = 0; i < 12; i++) {
            const angle = (i / 12) * 360 + Math.sin(i * 7) * 20;
            const endDist = 250 + (i % 3) * 200;
            const rad = (angle * Math.PI) / 180;
            const startRot = -30 + (i * 41) % 60;
            petals.push({
              // start position — on a circle around flower
              sx: Math.cos(rad) * startRadius,
              sy: Math.sin(rad) * startRadius,
              sr: startRot,
              // end position — far out
              x: Math.cos(rad) * endDist + (Math.sin(i * 4) * 60),
              y: Math.sin(rad) * endDist * 0.6,
              size: 80 + (i % 3) * 25,
              rot: startRot + (-100 + (i * 97) % 200),
              color: colors[i % colors.length],
              delay: i * 0.03,
              scale: 1 + (i % 3) * 0.15,
              dur: 1.8 + (i % 3) * 0.4,
            });
          }
          return petals.map((p, i) => (
            <svg
              key={i}
              className="absolute left-1/2 top-1/2"
              width={p.size} height={p.size}
              viewBox="0 0 80 80"
              style={{
                opacity: 0,
                marginLeft: -p.size / 2,
                marginTop: -p.size / 2,
                ['--sx' as string]: `${p.sx}px`,
                ['--sy' as string]: `${p.sy}px`,
                ['--sr' as string]: `${p.sr}deg`,
                ['--px' as string]: `${p.x}px`,
                ['--py' as string]: `${p.y}px`,
                ['--pr' as string]: `${p.rot}deg`,
                ['--ps' as string]: `${p.scale}`,
                animation: `petal-drift ${p.dur}s cubic-bezier(0.1,0.6,0.3,1) ${p.delay}s forwards`,
              }}
            >
              {/* Petal shape — soft, rounded like a cherry blossom petal */}
              <path
                d="M40 6 C22 10 8 24 8 42 C8 52 14 60 24 66 C30 70 36 74 40 76 C44 74 50 70 56 66 C66 60 72 52 72 42 C72 24 58 10 40 6Z"
                fill={p.color}
              />
              {/* Inner fold line — gives 3D depth */}
              <path
                d="M40 14 C38 28 38 48 40 68"
                stroke="white" strokeWidth="1.5" opacity="0.25" strokeLinecap="round" fill="none"
              />
              {/* Subtle highlight */}
              <ellipse cx="32" cy="32" rx="8" ry="14" fill="white" opacity="0.12" transform="rotate(-20 32 32)" />
            </svg>
          ));
        })()}
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

        {/* Bundles — fly in from center staggered, link to /bundles/[slug] */}
        {campaign.bundles.length > 0 && (
          <div className="grid grid-cols-2 sm:flex sm:justify-center gap-3 sm:gap-4">
            {campaign.bundles.slice(0, 4).map((b: CampaignBundle, i: number) => {
              const saved = b.bundle_original_price - b.bundle_price;
              const pct = saved > 0 ? Math.round((saved / b.bundle_original_price) * 100) : 0;
              return (
                <Link
                  key={b.id}
                  href={`/bundles/${b.slug}`}
                  className="sm:w-[220px] group relative bg-white rounded-3xl border border-[#e7d9cb]/60 shadow-md shadow-[#9F6B3E]/[0.04] hover:shadow-xl hover:shadow-[#9F6B3E]/[0.12] hover:-translate-y-2 transition-all duration-500"
                  style={{
                    opacity: 0,
                    animation: bursting ? `card-fly 0.9s cubic-bezier(0.16,1,0.3,1) ${0.15 + i * 0.12}s forwards` : 'none',
                  }}
                >
                  <div className="relative aspect-square overflow-hidden rounded-t-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3]">
                    {b.image ? (
                      <ImageWithFallback
                        src={imageUrl(b.image)!}
                        alt={b.name}
                        fill
                        sizes="220px"
                        className="object-cover transition-transform duration-700 group-hover:scale-105"
                      />
                    ) : (
                      <LogoPlaceholder />
                    )}
                  </div>
                  {pct > 0 && (
                    <div
                      className="absolute -top-5 -right-4 z-20"
                      style={{ animation: settled ? 'badge-wobble 4s ease-in-out infinite' : 'none' }}
                    >
                      <div className="relative w-[72px] h-[56px] sm:w-[84px] sm:h-[64px] flex items-center justify-center">
                        <svg className="absolute inset-0 w-full h-full drop-shadow-lg" viewBox="0 0 120 90" fill="none">
                          <path d="M30 70 C10 70 0 58 4 46 C0 36 8 24 22 22 C24 10 36 2 50 4 C58 -2 72 -2 82 6 C90 2 104 8 108 20 C118 26 120 42 112 52 C118 62 112 74 98 76 C94 84 82 90 70 86 C62 92 48 90 42 82 C34 86 20 80 30 70Z" fill="#c0392b" />
                        </svg>
                        <div className="relative text-center leading-none -mt-0.5">
                          <div className="text-white font-black text-xl sm:text-2xl" style={{ textShadow: '0 1px 4px rgba(0,0,0,0.15)' }}>{pct}</div>
                          <div className="text-white/80 font-black text-[7px] sm:text-[8px] tracking-wider -mt-0.5">%OFF</div>
                        </div>
                      </div>
                    </div>
                  )}
                  <div className="p-3.5">
                    <div className="text-sm font-bold text-gray-700 line-clamp-1 mb-2 group-hover:text-[#9F6B3E] transition-colors">
                      {b.name}
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="text-xl font-black text-[#c0392b]">
                        ${b.bundle_price.toLocaleString()}
                      </span>
                      {saved > 0 && (
                        <span className="text-[11px] text-gray-400 line-through">
                          ${b.bundle_original_price.toLocaleString()}
                        </span>
                      )}
                    </div>
                    {saved > 0 && (
                      <div className="mt-1.5 text-[11px] font-black text-[#c0392b]/70">
                        現省 ${saved.toLocaleString()}
                      </div>
                    )}
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
            { l: '3%', t: '15%', iconName: 'shield', color: '#4A9D5F', text: '官方正品', bg: 'bg-white', delay: 0 },
            { l: '88%', t: '12%', iconName: 'target', color: '#C0392B', text: '限量搶購', bg: 'bg-white', delay: 0.8 },
            { l: '2%', t: '72%', iconName: 'ribbon', color: '#E0748C', text: '搭配更划算', bg: 'bg-white', delay: 0.4 },
            { l: '15%', t: '90%', iconName: 'truck', color: '#D4762C', text: '快速出貨', bg: 'bg-white', delay: 1.6 },
            { l: '75%', t: '5%', iconName: 'sparkle', color: '#E8A93B', text: 'VIP 優惠', bg: 'bg-white', delay: 0.6 },
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
              <SiteIcon name={tag.iconName} size={16} color={tag.color} />
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
