'use client';

/**
 * 健康 × 美容 雙敘事區段 — 滾動綁定的對比故事。
 * 左半：健康／保健 (nature, leaves, warmth)
 * 右半：美容／光彩 (glow, sparkle, bloom)
 * 滾進視窗時兩半從中央分開、各自上浮揭露，伴隨文字 stagger reveal。
 */

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';

export default function HealthBeautyNarrative() {
  const ref = useRef<HTMLDivElement>(null);
  const [progress, setProgress] = useState(0); // 0..1 based on scroll within section

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      setProgress(1);
      return;
    }

    let ticking = false;
    const update = () => {
      const rect = el.getBoundingClientRect();
      const vh = window.innerHeight;
      // Enter: when top reaches 80% of viewport; exit: when bottom reaches 20%
      const enter = vh * 0.9;
      const exit = vh * 0.1;
      const raw = (enter - rect.top) / (enter - exit);
      setProgress(Math.max(0, Math.min(1, raw)));
      ticking = false;
    };
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(update);
    };
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    };
  }, []);

  const splitOffset = (1 - progress) * 12; // vw
  const textOpacity = Math.max(0, progress * 1.4 - 0.2);
  const textY = (1 - progress) * 40;

  return (
    <section ref={ref} className="relative py-20 sm:py-28 overflow-hidden bg-[#faf5ee]">
      {/* Two panels sliding apart */}
      <div className="relative grid grid-cols-2 gap-0 min-h-[420px] sm:min-h-[520px]">
        {/* HEALTH panel */}
        <div
          className="relative overflow-hidden rounded-r-[80px] sm:rounded-r-[140px]"
          style={{
            transform: `translateX(-${splitOffset}vw)`,
            transition: 'transform 0.1s linear',
            background:
              'linear-gradient(135deg, #cfe3c9 0%, #9fc19a 45%, #7ba876 100%)',
          }}
        >
          <div className="absolute inset-0">
            <svg className="w-full h-full opacity-20" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice" aria-hidden>
              <path d="M 50 300 Q 100 150 200 180 T 380 120" stroke="white" strokeWidth="2" fill="none" />
              <circle cx="60" cy="80" r="40" fill="white" opacity="0.3" />
              <circle cx="340" cy="260" r="60" fill="white" opacity="0.25" />
              <path d="M 80 360 C 140 340, 180 380, 240 350" stroke="white" strokeWidth="2" fill="none" opacity="0.5" />
              {/* Leaves */}
              <g transform="translate(140 200) rotate(-20)" opacity="0.35">
                <ellipse cx="0" cy="0" rx="40" ry="18" fill="white" />
                <line x1="-40" y1="0" x2="40" y2="0" stroke="rgba(0,0,0,0.3)" strokeWidth="1" />
              </g>
              <g transform="translate(260 140) rotate(25)" opacity="0.3">
                <ellipse cx="0" cy="0" rx="30" ry="14" fill="white" />
                <line x1="-30" y1="0" x2="30" y2="0" stroke="rgba(0,0,0,0.3)" strokeWidth="1" />
              </g>
            </svg>
          </div>
          <div className="relative h-full flex items-center justify-end pr-4 sm:pr-16 py-8">
            <div className="text-right max-w-[220px] sm:max-w-xs" style={{ opacity: textOpacity, transform: `translateY(${textY}px)`, transition: 'opacity 0.4s, transform 0.6s cubic-bezier(0.2, 0.9, 0.3, 1)' }}>
              <div className="text-4xl sm:text-6xl mb-2">🌿</div>
              <div className="text-[10px] sm:text-xs font-black tracking-[0.3em] text-white/90 mb-2">HEALTH · 內在健康</div>
              <h3 className="text-2xl sm:text-4xl font-black text-white leading-tight mb-3">
                由內而外<br />
                綻放自然
              </h3>
              <p className="text-sm text-white/90 leading-relaxed">
                葉黃素、益生菌、酵素、葉黃素晶亮凍⋯<br />
                每一顆都是營養師嚴選配方，守護仙女每一天的活力。
              </p>
            </div>
          </div>
        </div>

        {/* BEAUTY panel */}
        <div
          className="relative overflow-hidden rounded-l-[80px] sm:rounded-l-[140px]"
          style={{
            transform: `translateX(${splitOffset}vw)`,
            transition: 'transform 0.1s linear',
            background:
              'linear-gradient(135deg, #f7c79a 0%, #e7a77e 50%, #c8835a 100%)',
          }}
        >
          <div className="absolute inset-0">
            <svg className="w-full h-full opacity-25" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice" aria-hidden>
              {/* Sparkles */}
              {[...Array(12)].map((_, i) => {
                const cx = (i * 73) % 400;
                const cy = (i * 131) % 380 + 10;
                const r = 1 + (i % 3);
                return <circle key={i} cx={cx} cy={cy} r={r} fill="white" />;
              })}
              {/* Bloom */}
              <g transform="translate(300 120)">
                <circle r="40" fill="white" opacity="0.25" />
                <circle r="20" fill="white" opacity="0.35" />
              </g>
              <g transform="translate(80 300)">
                <circle r="60" fill="white" opacity="0.15" />
                <circle r="30" fill="white" opacity="0.25" />
              </g>
              {/* Sparkle stars */}
              <g transform="translate(150 80)" fill="white" opacity="0.6">
                <path d="M0 -14 L2 -2 L14 0 L2 2 L0 14 L-2 2 L-14 0 L-2 -2 Z" />
              </g>
              <g transform="translate(320 300)" fill="white" opacity="0.5">
                <path d="M0 -10 L1.5 -1.5 L10 0 L1.5 1.5 L0 10 L-1.5 1.5 L-10 0 L-1.5 -1.5 Z" />
              </g>
            </svg>
          </div>
          <div className="relative h-full flex items-center justify-start pl-4 sm:pl-16 py-8">
            <div className="text-left max-w-[220px] sm:max-w-xs" style={{ opacity: textOpacity, transform: `translateY(${textY}px)`, transition: 'opacity 0.4s, transform 0.6s cubic-bezier(0.2, 0.9, 0.3, 1)' }}>
              <div className="text-4xl sm:text-6xl mb-2">✨</div>
              <div className="text-[10px] sm:text-xs font-black tracking-[0.3em] text-white/95 mb-2">BEAUTY · 外在光彩</div>
              <h3 className="text-2xl sm:text-4xl font-black text-white leading-tight mb-3">
                水光透亮<br />
                仙女氣場
              </h3>
              <p className="text-sm text-white/95 leading-relaxed">
                水光錠、水光繃帶面膜、雪聚露、婕肌零⋯<br />
                專利口服玻尿酸 × 外在保養雙軌並行，活出仙女光芒。
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* CTA bar */}
      <div className="mt-12 text-center px-4" style={{ opacity: textOpacity }}>
        <p className="text-sm sm:text-base text-slate-600 mb-5 max-w-xl mx-auto leading-relaxed">
          健康與美麗，不是二選一。婕樂纖全系列為妳雙向守護。
        </p>
        <Link
          href="/products"
          className="inline-flex items-center gap-2 px-8 py-3.5 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/30 hover:bg-[#85572F] hover:shadow-xl hover:shadow-[#9F6B3E]/40 transition-all min-h-[48px]"
        >
          探索全系列
          <span className="inline-block transition-transform group-hover:translate-x-1">→</span>
        </Link>
      </div>
    </section>
  );
}
