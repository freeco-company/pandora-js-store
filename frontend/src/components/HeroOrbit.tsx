'use client';

/**
 * Hero orbit visual — central glowing orb + floating badges.
 * Responsive: `size` controls the orb; badges scale proportionally.
 *
 *   <HeroOrbit size={420} />  // desktop
 *   <HeroOrbit size={260} /> // mobile
 */

interface Props {
  size?: number;
  className?: string;
}

export default function HeroOrbit({ size = 420, className = '' }: Props) {
  // Scale all badge sizes proportionally to the orb
  const s = size / 420; // 1.0 at full, ~0.62 at 260

  const bigBadge = Math.round(112 * s);
  const midBadge = Math.round(96 * s);
  const smBadge = Math.round(64 * s);
  const bigEmoji = Math.round(36 * s);
  const midEmoji = Math.round(30 * s);
  const smEmoji = Math.round(22 * s);
  const label = Math.round(10 * s);

  return (
    <div
      className={`relative pointer-events-none ${className}`}
      style={{ width: size, height: size }}
      aria-hidden
    >
      {/* Central orb */}
      <div
        className="absolute inset-0 rounded-full hero-orb"
        style={{
          background: 'radial-gradient(circle at 30% 30%, #ffffff, #f7c79a 45%, #9F6B3E 100%)',
          boxShadow:
            '0 30px 80px -20px rgba(159, 107, 62, 0.5), inset 0 0 80px rgba(255,255,255,0.4)',
        }}
      />

      {/* 健康內在 (right-top) */}
      <div
        className="absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-a rounded-3xl"
        style={{
          width: bigBadge,
          height: bigBadge,
          top: '6%',
          right: '-10%',
        }}
      >
        <div style={{ fontSize: bigEmoji, lineHeight: 1 }}>🌿</div>
        <div className="font-black text-[#4A9D5F] mt-1" style={{ fontSize: label }}>
          健康內在
        </div>
      </div>

      {/* 美容外在 (right-bottom) */}
      <div
        className="absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-b rounded-3xl"
        style={{
          width: bigBadge,
          height: bigBadge,
          bottom: '2%',
          right: '-6%',
        }}
      >
        <div style={{ fontSize: bigEmoji, lineHeight: 1 }}>✨</div>
        <div className="font-black text-[#E0748C] mt-1" style={{ fontSize: label }}>
          美容外在
        </div>
      </div>

      {/* 玉山獎 (left-bottom) */}
      <div
        className="absolute bg-gradient-to-br from-[#9F6B3E] to-[#85572F] shadow-2xl flex flex-col items-center justify-center hero-orbit-c text-white rounded-3xl"
        style={{
          width: midBadge,
          height: midBadge,
          bottom: '14%',
          left: '-10%',
        }}
      >
        <div style={{ fontSize: midEmoji, lineHeight: 1 }}>🏆</div>
        <div className="font-black mt-1" style={{ fontSize: label }}>
          玉山獎
        </div>
      </div>

      {/* 💎 bubble (left-middle) */}
      <div
        className="absolute bg-white/90 backdrop-blur shadow-lg flex items-center justify-center hero-orbit-d rounded-full"
        style={{
          width: smBadge,
          height: smBadge,
          top: '32%',
          left: '-14%',
        }}
      >
        <span style={{ fontSize: smEmoji, lineHeight: 1 }}>💎</span>
      </div>

      <style jsx>{`
        @keyframes hero-orb-float {
          0%, 100% { transform: translate(0, 0) scale(1); }
          50% { transform: translate(10px, -12px) scale(1.03); }
        }
        :global(.hero-orb) { animation: hero-orb-float 6s ease-in-out infinite; will-change: transform; }

        @keyframes hero-orbit-a {
          0%, 100% { transform: translate(0, 0) rotate(-4deg); }
          50% { transform: translate(-8px, 14px) rotate(2deg); }
        }
        @keyframes hero-orbit-b {
          0%, 100% { transform: translate(0, 0) rotate(3deg); }
          50% { transform: translate(10px, -14px) rotate(-2deg); }
        }
        @keyframes hero-orbit-c {
          0%, 100% { transform: translate(0, 0) rotate(-6deg); }
          50% { transform: translate(8px, -12px) rotate(4deg); }
        }
        @keyframes hero-orbit-d {
          0%, 100% { transform: translate(0, 0); }
          50% { transform: translate(-10px, 18px); }
        }
        :global(.hero-orbit-a) { animation: hero-orbit-a 8s ease-in-out infinite; will-change: transform; }
        :global(.hero-orbit-b) { animation: hero-orbit-b 9s ease-in-out infinite 1s; will-change: transform; }
        :global(.hero-orbit-c) { animation: hero-orbit-c 10s ease-in-out infinite 2s; will-change: transform; }
        :global(.hero-orbit-d) { animation: hero-orbit-d 7s ease-in-out infinite 0.5s; will-change: transform; }

        @media (prefers-reduced-motion: reduce), (hover: none) {
          /* Mobile / touch devices get a static orbit — saves continuous main-thread animation */
          :global(.hero-orb), :global(.hero-orbit-a), :global(.hero-orbit-b),
          :global(.hero-orbit-c), :global(.hero-orbit-d) { animation: none; }
        }
      `}</style>
    </div>
  );
}
