'use client';

/**
 * Hero orbit visual — central glowing orb + floating badges + orbit rings.
 * Responsive: `size` controls the orb; badges scale proportionally.
 */

interface Props {
  size?: number;
  className?: string;
}

export default function HeroOrbit({ size = 420, className = '' }: Props) {
  const s = size / 420;

  const bigBadge = Math.round(100 * s);
  const midBadge = Math.round(80 * s);
  const smBadge = Math.round(56 * s);
  const bigEmoji = Math.round(32 * s);
  const midEmoji = Math.round(26 * s);
  const smEmoji = Math.round(20 * s);
  const label = Math.round(10 * s);
  const tinyDot = Math.round(8 * s);

  return (
    <div
      className={`relative pointer-events-none ${className}`}
      style={{ width: size, height: size }}
      aria-hidden
    >
      {/* Orbit ring paths */}
      <svg className="absolute inset-0 w-full h-full hero-ring-spin" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="44" fill="none" stroke="#9F6B3E" strokeWidth="0.15" opacity="0.25" strokeDasharray="3 5" />
        <circle cx="50" cy="50" r="52" fill="none" stroke="#9F6B3E" strokeWidth="0.1" opacity="0.15" strokeDasharray="2 6" />
      </svg>

      {/* Central orb */}
      <div
        className="absolute rounded-full hero-orb"
        style={{
          inset: '8%',
          background: 'radial-gradient(circle at 30% 30%, #ffffff, #f7c79a 45%, #c9935a 80%, #9F6B3E 100%)',
          boxShadow:
            '0 30px 80px -20px rgba(159, 107, 62, 0.5), inset 0 0 80px rgba(255,255,255,0.4)',
        }}
      />

      {/* Inner glow pulse */}
      <div
        className="absolute rounded-full hero-pulse"
        style={{
          inset: '15%',
          background: 'radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%)',
        }}
      />

      {/* 健康內在 (right-top) */}
      <div
        className="absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-a rounded-2xl"
        style={{
          width: bigBadge, height: bigBadge,
          top: '4%', right: '-8%',
        }}
      >
        <div style={{ fontSize: bigEmoji, lineHeight: 1 }}>🌿</div>
        <div className="font-black text-[#4A9D5F] mt-0.5" style={{ fontSize: label }}>健康內在</div>
      </div>

      {/* 美容外在 (right-bottom) */}
      <div
        className="absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-b rounded-2xl"
        style={{
          width: bigBadge, height: bigBadge,
          bottom: '4%', right: '-4%',
        }}
      >
        <div style={{ fontSize: bigEmoji, lineHeight: 1 }}>✨</div>
        <div className="font-black text-[#E0748C] mt-0.5" style={{ fontSize: label }}>美容外在</div>
      </div>

      {/* 玉山獎 (left-bottom) */}
      <div
        className="absolute bg-gradient-to-br from-[#9F6B3E] to-[#85572F] shadow-2xl flex flex-col items-center justify-center hero-orbit-c text-white rounded-2xl"
        style={{
          width: midBadge, height: midBadge,
          bottom: '12%', left: '-8%',
        }}
      >
        <div style={{ fontSize: midEmoji, lineHeight: 1 }}>🏆</div>
        <div className="font-black mt-0.5" style={{ fontSize: label }}>玉山獎</div>
      </div>

      {/* 健康食品認證 (top-left) */}
      <div
        className="absolute bg-gradient-to-br from-[#e8f5e9] to-[#c8e6c9] shadow-lg flex flex-col items-center justify-center hero-orbit-e rounded-2xl border border-[#a5d6a7]"
        style={{
          width: midBadge, height: midBadge,
          top: '18%', left: '-12%',
        }}
      >
        <div style={{ fontSize: midEmoji, lineHeight: 1 }}>🏅</div>
        <div className="font-black text-[#2e7d32] mt-0.5" style={{ fontSize: label }}>小綠人認證</div>
      </div>

      {/* 💎 VIP bubble (left-middle) */}
      <div
        className="absolute bg-white/90 backdrop-blur shadow-lg flex items-center justify-center hero-orbit-d rounded-full"
        style={{
          width: smBadge, height: smBadge,
          top: '40%', left: '-14%',
        }}
      >
        <span style={{ fontSize: smEmoji, lineHeight: 1 }}>💎</span>
      </div>

      {/* 3 階梯 (bottom-center) */}
      <div
        className="absolute bg-white/95 backdrop-blur-sm shadow-lg flex flex-col items-center justify-center hero-orbit-f rounded-xl"
        style={{
          width: midBadge, height: Math.round(midBadge * 0.7),
          bottom: '-4%', left: '30%',
        }}
      >
        <div className="flex gap-0.5" style={{ fontSize: Math.round(smEmoji * 0.7) }}>
          <span>🌱</span><span>🎀</span><span>👑</span>
        </div>
        <div className="font-black text-[#9F6B3E] mt-0.5" style={{ fontSize: label }}>3 階梯定價</div>
      </div>

      {/* Floating micro particles */}
      {[
        { top: '8%', left: '20%', delay: '0s' },
        { top: '65%', right: '8%', delay: '1.5s' },
        { bottom: '25%', left: '5%', delay: '3s' },
        { top: '35%', right: '3%', delay: '2s' },
        { bottom: '8%', left: '55%', delay: '0.8s' },
      ].map((pos, i) => (
        <div
          key={i}
          className="absolute rounded-full hero-particle"
          style={{
            width: tinyDot, height: tinyDot,
            background: 'radial-gradient(circle, rgba(159,107,62,0.4), transparent)',
            animationDelay: pos.delay,
            ...pos,
          }}
        />
      ))}

      <style jsx>{`
        @keyframes hero-orb-float {
          0%, 100% { transform: translate(0, 0) scale(1); }
          50% { transform: translate(8px, -10px) scale(1.02); }
        }
        :global(.hero-orb) { animation: hero-orb-float 6s ease-in-out infinite; will-change: transform; }

        @keyframes hero-pulse {
          0%, 100% { opacity: 0.3; transform: scale(0.95); }
          50% { opacity: 0.6; transform: scale(1.05); }
        }
        :global(.hero-pulse) { animation: hero-pulse 4s ease-in-out infinite; }

        @keyframes hero-ring-spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
        :global(.hero-ring-spin) { animation: hero-ring-spin 60s linear infinite; }

        @keyframes hero-orbit-a {
          0%, 100% { transform: translate(0, 0) rotate(-3deg); }
          50% { transform: translate(-6px, 12px) rotate(2deg); }
        }
        @keyframes hero-orbit-b {
          0%, 100% { transform: translate(0, 0) rotate(2deg); }
          50% { transform: translate(8px, -12px) rotate(-2deg); }
        }
        @keyframes hero-orbit-c {
          0%, 100% { transform: translate(0, 0) rotate(-5deg); }
          50% { transform: translate(6px, -10px) rotate(3deg); }
        }
        @keyframes hero-orbit-d {
          0%, 100% { transform: translate(0, 0); }
          50% { transform: translate(-8px, 14px); }
        }
        @keyframes hero-orbit-e {
          0%, 100% { transform: translate(0, 0) rotate(2deg); }
          50% { transform: translate(10px, 8px) rotate(-3deg); }
        }
        @keyframes hero-orbit-f {
          0%, 100% { transform: translate(0, 0) rotate(-1deg); }
          50% { transform: translate(-6px, -8px) rotate(1deg); }
        }
        @keyframes hero-particle {
          0%, 100% { opacity: 0.2; transform: scale(0.5) translateY(0); }
          50% { opacity: 0.8; transform: scale(1.2) translateY(-10px); }
        }
        :global(.hero-orbit-a) { animation: hero-orbit-a 8s ease-in-out infinite; will-change: transform; }
        :global(.hero-orbit-b) { animation: hero-orbit-b 9s ease-in-out infinite 1s; will-change: transform; }
        :global(.hero-orbit-c) { animation: hero-orbit-c 10s ease-in-out infinite 2s; will-change: transform; }
        :global(.hero-orbit-d) { animation: hero-orbit-d 7s ease-in-out infinite 0.5s; will-change: transform; }
        :global(.hero-orbit-e) { animation: hero-orbit-e 11s ease-in-out infinite 1.5s; will-change: transform; }
        :global(.hero-orbit-f) { animation: hero-orbit-f 9s ease-in-out infinite 3s; will-change: transform; }
        :global(.hero-particle) { animation: hero-particle 5s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
          :global(.hero-orb), :global(.hero-pulse), :global(.hero-ring-spin),
          :global(.hero-orbit-a), :global(.hero-orbit-b), :global(.hero-orbit-c),
          :global(.hero-orbit-d), :global(.hero-orbit-e), :global(.hero-orbit-f),
          :global(.hero-particle) { animation: none; }
        }
      `}</style>
    </div>
  );
}
