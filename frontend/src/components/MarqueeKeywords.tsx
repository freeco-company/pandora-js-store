'use client';

/** 無限橫向滾動關鍵字帶，hover 暫停 */

const KEYWORDS = [
  '✨ 水光透亮',
  '🌿 由內而外',
  '💎 官方正品',
  '🌸 仙女光芒',
  '🔥 玉山獎雙首獎',
  '🏆 國家品牌肯定',
  '💧 口服玻尿酸',
  '🌱 110 種酵素',
  '🥛 益生菌配方',
  '👑 仙女專屬',
];

export default function MarqueeKeywords() {
  const row = [...KEYWORDS, ...KEYWORDS];

  return (
    <section className="py-6 sm:py-8 overflow-hidden border-y border-[#e7d9cb] bg-gradient-to-r from-[#fdf7ef] via-[#f7eee3] to-[#fdf7ef]">
      <div className="marquee-track flex gap-8 whitespace-nowrap">
        {row.map((kw, i) => (
          <span
            key={i}
            className="text-lg sm:text-2xl font-black text-[#9F6B3E]/80 shrink-0 tracking-wide"
            style={{ fontFamily: '"Microsoft JhengHei", sans-serif' }}
          >
            {kw}
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
