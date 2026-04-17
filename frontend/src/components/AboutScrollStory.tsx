'use client';

/**
 * /about scrollytelling — continuous flow, not scene-swapping.
 *
 * Pattern: sticky visual panel + scrolling text sections.
 * As each text section enters the viewport, the visual evolves
 * (scale, color, elements appear) via CSS transitions — not JS
 * animation frames. Feels like one long page, not separate acts.
 *
 * Inspired by artbank.tfaf.org.tw / fenc.com / touchstone.tw style.
 */

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';

const SECTIONS = [
  {
    id: 'seed',
    tagline: 'THE BEGINNING',
    title: '每位女性心裡，都住著一位仙女',
    body: '只是有時候忘了。忘了自己可以更好、可以更自信、可以不用將就。婕樂纖是那個提醒你的契機 — 不是變成別人，而是找回自己。',
  },
  {
    id: 'grow',
    tagline: 'DISCOVERY',
    title: '遇見婕樂纖的那一天',
    body: '從一盒開始嘗試。三階梯定價讓你沒有門檻壓力，組合價和 VIP 價讓持續變得划算。一顆種子，悄悄種在心裡。',
  },
  {
    id: 'transform',
    tagline: 'TRANSFORMATION',
    title: '改變，正在發生',
    body: '營養師陪伴班和陪跑班不只是服務，是一群人陪你走的旅程。飲食指導、持續追蹤、階段檢視 — 你不是一個人在堅持。',
  },
  {
    id: 'bloom',
    tagline: 'BLOSSOM',
    title: '從仙女，到潘朵拉',
    body: '花開了。不是因為外在的改變，而是因為你打開了那個屬於自己的盒子。潘朵拉的盒子裡裝的不是災難 — 是希望、是健康、是自信。',
    cta: true,
  },
];

// Lerp helper
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

export default function AboutScrollStory() {
  const [activeIdx, setActiveIdx] = useState(0);
  const sectionRefs = useRef<(HTMLDivElement | null)[]>([]);

  useEffect(() => {
    const observers = sectionRefs.current.map((el, i) => {
      if (!el) return null;
      const obs = new IntersectionObserver(
        ([entry]) => {
          if (entry.isIntersecting) setActiveIdx(i);
        },
        { threshold: 0.5, rootMargin: '-10% 0px -40% 0px' },
      );
      obs.observe(el);
      return obs;
    });
    return () => observers.forEach((obs) => obs?.disconnect());
  }, []);

  // Visual state derived from active section
  const progress = activeIdx / (SECTIONS.length - 1); // 0→1

  return (
    <div className="relative">
      {/* ── Sticky visual panel (left on desktop, top on mobile) ── */}
      <div className="lg:flex lg:min-h-screen">
        {/* Visual — sticky */}
        <div className="lg:sticky lg:top-0 lg:h-screen lg:w-1/2 flex items-center justify-center overflow-hidden transition-colors duration-1000"
          style={{
            backgroundColor: [
              '#1a1410', // seed: dark
              '#1e2e1a', // grow: forest
              '#fdf7ef', // transform: warm cream
              '#fdf7ef', // bloom: warm cream
            ][activeIdx] || '#1a1410',
          }}
        >
          {/* Mobile: fixed height. Desktop: full viewport */}
          <div className="h-[50vh] lg:h-full w-full flex items-center justify-center relative p-8">
            <MorphingVisual progress={progress} activeIdx={activeIdx} />
          </div>
        </div>

        {/* Text sections — scroll naturally */}
        <div className="lg:w-1/2">
          {SECTIONS.map((s, i) => (
            <div
              key={s.id}
              ref={(el) => { sectionRefs.current[i] = el; }}
              className="min-h-screen flex items-center"
            >
              <div className={`p-8 sm:p-12 lg:p-16 max-w-xl transition-all duration-700 ${activeIdx === i ? 'opacity-100 translate-y-0 blur-0' : 'opacity-20 translate-y-8 blur-[2px]'}`}>
                <div className="text-[10px] font-black tracking-[0.4em] mb-4" style={{
                  color: i <= 1 ? 'rgba(231,217,203,0.7)' : '#9F6B3E',
                }}>{s.tagline}</div>
                <h2 className="text-2xl sm:text-3xl lg:text-4xl font-black leading-tight tracking-tight" style={{
                  color: i <= 1 ? '#f7eee3' : '#3d2e22',
                }}>{s.title}</h2>
                <p className="mt-5 text-sm sm:text-base leading-relaxed" style={{
                  color: i <= 1 ? 'rgba(247,238,227,0.6)' : 'rgba(61,46,34,0.6)',
                }}>{s.body}</p>
                {s.cta && (
                  <div className="mt-8">
                    <Link
                      href="/products"
                      className="inline-flex items-center gap-2 px-7 py-3.5 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-all shadow-lg shadow-[#9F6B3E]/20 min-h-[48px]"
                    >
                      開始我的蛻變旅程 →
                    </Link>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

/**
 * The visual that lives in the sticky panel.
 * Morphs continuously based on which text section is active.
 * All transitions via CSS (duration-1000) for buttery feel.
 */
function MorphingVisual({ progress, activeIdx }: { progress: number; activeIdx: number }) {
  // SVG attributes transition via inline style + CSS transition
  const stemH = lerp(5, 34, progress);
  const bodyR = lerp(0, 12, Math.max(0, progress - 0.2) / 0.8);
  const leafScale = lerp(0, 1, Math.max(0, (progress - 0.15) / 0.5));
  const petalScale = lerp(0, 1, Math.max(0, (progress - 0.6) / 0.4));
  const faceOpacity = progress > 0.3 ? 1 : 0;
  const sparkleOpacity = progress > 0.85 ? 1 : 0;
  const glowOpacity = lerp(0, 0.4, Math.max(0, (progress - 0.7) / 0.3));

  const svgSize = activeIdx >= 2 ? 'w-56 h-56 sm:w-72 sm:h-72 lg:w-80 lg:h-80' : 'w-40 h-40 sm:w-56 sm:h-56 lg:w-64 lg:h-64';

  return (
    <div className="relative flex items-center justify-center">
      {/* Glow backdrop */}
      <div
        className="absolute w-[120%] h-[120%] rounded-full transition-all duration-1000"
        style={{
          background: `radial-gradient(circle, rgba(255,205,210,${glowOpacity}) 0%, transparent 70%)`,
          transform: `scale(${lerp(0.5, 1.3, progress)})`,
        }}
      />

      <svg viewBox="0 0 100 100" className={`relative transition-all duration-700 ${svgSize}`}>
        <defs>
          <linearGradient id="stPetal" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stopColor="#ffecf1" />
            <stop offset="100%" stopColor="#ff8fa8" />
          </linearGradient>
          <linearGradient id="stLeaf" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#a7e3a7" />
            <stop offset="100%" stopColor="#6faa6f" />
          </linearGradient>
        </defs>

        {/* Pot — fades in at 25%+ */}
        <g className="transition-opacity duration-1000" style={{ opacity: progress > 0.25 ? 1 : 0 }}>
          <ellipse cx="50" cy="90" rx="22" ry="5" fill="rgba(0,0,0,0.08)" />
          <rect x="32" y="72" width="36" height="18" rx="3" fill="#b8826b" />
          <rect x="30" y="70" width="40" height="5" rx="2" fill="#9F6B3E" />
        </g>

        {/* Seed — visible early, fades as stem grows */}
        <g className="transition-opacity duration-700" style={{ opacity: progress < 0.2 ? 1 : 0 }}>
          <ellipse cx="50" cy="75" rx="6" ry="8" fill="#8B6914" />
          <ellipse cx="50" cy="73" rx="4" ry="5" fill="#A67C00" />
        </g>

        {/* Stem */}
        <path
          d={`M50 72 Q50 ${72 - stemH * 0.5} 50 ${72 - stemH}`}
          stroke="#7ab87a"
          strokeWidth={lerp(1.5, 3, progress)}
          fill="none"
          strokeLinecap="round"
          className="transition-all duration-700"
        />

        {/* Leaves */}
        <g className="transition-all duration-700" style={{ opacity: leafScale }}>
          <ellipse cx={50 - lerp(0, 14, leafScale)} cy={72 - stemH + 10} rx={lerp(0, 10, leafScale)} ry={lerp(0, 5.5, leafScale)} fill="url(#stLeaf)" transform={`rotate(-25 ${50 - lerp(0, 14, leafScale)} ${72 - stemH + 10})`} />
          <ellipse cx={50 + lerp(0, 14, leafScale)} cy={72 - stemH + 10} rx={lerp(0, 10, leafScale)} ry={lerp(0, 5.5, leafScale)} fill="url(#stLeaf)" transform={`rotate(25 ${50 + lerp(0, 14, leafScale)} ${72 - stemH + 10})`} />
        </g>

        {/* Body */}
        {bodyR > 1 && (
          <circle cx="50" cy={72 - stemH} r={bodyR} fill="#9edc9e" className="transition-all duration-700" />
        )}

        {/* Petals */}
        <g className="transition-all duration-1000" style={{ opacity: petalScale }}>
          {[0, 72, 144, 216, 288].map((deg) => (
            <g key={deg} transform={`rotate(${deg} 50 ${72 - stemH - bodyR - 4})`}>
              <ellipse
                cx="50"
                cy={72 - stemH - bodyR - 4 - lerp(0, 10, petalScale)}
                rx={lerp(0, 7, petalScale)}
                ry={lerp(0, 5, petalScale)}
                fill="url(#stPetal)"
              />
            </g>
          ))}
          <circle cx="50" cy={72 - stemH - bodyR - 4} r={lerp(0, 3, petalScale)} fill="#fff176" />
        </g>

        {/* Face */}
        <g className="transition-opacity duration-500" style={{ opacity: faceOpacity }}>
          {activeIdx >= 3 ? (
            // Excited
            <>
              <path d={`M44 ${70 - stemH} Q46 ${68 - stemH} 48 ${70 - stemH}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
              <path d={`M52 ${70 - stemH} Q54 ${68 - stemH} 56 ${70 - stemH}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
              <path d={`M46 ${75 - stemH} Q50 ${79 - stemH} 54 ${75 - stemH}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
            </>
          ) : (
            // Neutral/happy
            <>
              <circle cx="46" cy={70 - stemH} r="1.3" fill="#3d2e22" />
              <circle cx="54" cy={70 - stemH} r="1.3" fill="#3d2e22" />
              <path d={`M47 ${75 - stemH} Q50 ${77 - stemH} 53 ${75 - stemH}`} stroke="#3d2e22" strokeWidth="1.2" fill="none" strokeLinecap="round" />
            </>
          )}
        </g>

        {/* Sparkles */}
        <g className="transition-opacity duration-700 icon-pulse" style={{ opacity: sparkleOpacity }}>
          <path d="M28 30 l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 l2.5 -1 z" fill="#fff9c4" />
          <path d="M72 24 l0.8 1.8 l1.8 0.8 l-1.8 0.8 l-0.8 1.8 l-0.8 -1.8 l-1.8 -0.8 l1.8 -0.8 z" fill="#fff9c4" />
          <path d="M24 50 l0.6 1.2 l1.2 0.6 l-1.2 0.6 l-0.6 1.2 l-0.6 -1.2 l-1.2 -0.6 l1.2 -0.6 z" fill="#fff9c4" />
        </g>
      </svg>
    </div>
  );
}
