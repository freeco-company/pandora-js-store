'use client';

import SiteIcon from '@/components/SiteIcon';

/** 無限橫向滾動關鍵字帶，hover 暫停 */

const KEYWORDS: { icon: string; color: string; text: string }[] = [
  { icon: 'sparkle', color: '#E8A93B', text: '水光透亮' },
  { icon: 'leaf', color: '#4A9D5F', text: '由內而外' },
  { icon: 'diamond', color: '#5BA3CF', text: '官方正品' },
  { icon: 'cherry-blossom', color: '#E0748C', text: '仙女光芒' },
  { icon: 'fire', color: '#D4582A', text: '玉山獎雙首獎' },
  { icon: 'trophy', color: '#E8A93B', text: '國家品牌肯定' },
  { icon: 'water-drop', color: '#4A90C4', text: '口服玻尿酸' },
  { icon: 'sprout', color: '#4A9D5F', text: '110 種酵素' },
  { icon: 'milk', color: '#9F6B3E', text: '益生菌配方' },
  { icon: 'crown', color: '#D4922A', text: '仙女專屬' },
];

export default function MarqueeKeywords() {
  const row = [...KEYWORDS, ...KEYWORDS];

  return (
    <section className="py-6 sm:py-8 overflow-hidden border-y border-[#e7d9cb] bg-gradient-to-r from-[#fdf7ef] via-[#f7eee3] to-[#fdf7ef]">
      <div className="marquee-track flex gap-8 whitespace-nowrap">
        {row.map((kw, i) => (
          <span
            key={i}
            className="text-lg sm:text-2xl font-black text-[#9F6B3E]/80 shrink-0 tracking-wide inline-flex items-center gap-2"
            style={{ fontFamily: '"Microsoft JhengHei", sans-serif' }}
          >
            <SiteIcon name={kw.icon} size={22} color={kw.color} />
            {kw.text}
            <span className="ml-8 text-[#9F6B3E]/30">·</span>
          </span>
        ))}
      </div>
      <style jsx>{`
        @keyframes marqueeSlide {
          from { transform: translate3d(0, 0, 0); }
          to { transform: translate3d(-50%, 0, 0); }
        }
        .marquee-track {
          animation: marqueeSlide 40s linear infinite;
          will-change: transform;
        }
        .marquee-track:hover { animation-play-state: paused; }
        @media (prefers-reduced-motion: reduce) {
          .marquee-track { animation: none; }
        }
      `}</style>
    </section>
  );
}
