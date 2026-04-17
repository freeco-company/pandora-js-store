'use client';

/**
 * /about 全螢幕 scroll story：從仙女到潘朵拉
 *
 * 5 幕：種子 → 萌芽 → 成長 → 蛻變 → 綻放
 * 每一幕的 SVG、文字、背景色都隨 scroll progress 連續變化。
 */

import ScrollStory, { type Act } from './ScrollStory';

const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

function ease(t: number) {
  return t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t;
}

// ── Act 1: 種子 ──────────────────────────────────────────────
function SeedScene({ progress }: { progress: number }) {
  const p = ease(progress);
  const seedScale = lerp(0.3, 1, p);
  const textOpacity = p < 0.2 ? p * 5 : p > 0.8 ? (1 - p) * 5 : 1;

  return (
    <div className="relative w-full h-full flex flex-col items-center justify-center px-8">
      {/* Seed SVG */}
      <svg viewBox="0 0 100 100" className="w-32 h-32 sm:w-48 sm:h-48" style={{ transform: `scale(${seedScale})` }}>
        <ellipse cx="50" cy="80" rx="30" ry="6" fill="rgba(255,255,255,0.05)" />
        <ellipse cx="50" cy="65" rx={lerp(4, 8, p)} ry={lerp(5, 10, p)} fill="#8B6914" />
        <ellipse cx="50" cy="62" rx={lerp(3, 6, p)} ry={lerp(3, 7, p)} fill="#A67C00" />
        {/* Tiny crack opening */}
        {p > 0.5 && (
          <path
            d={`M${50 - lerp(0, 2, (p - 0.5) * 2)} ${65 - lerp(0, 4, (p - 0.5) * 2)} Q50 ${60 - lerp(0, 6, (p - 0.5) * 2)} ${50 + lerp(0, 2, (p - 0.5) * 2)} ${65 - lerp(0, 4, (p - 0.5) * 2)}`}
            stroke="#7ab87a"
            strokeWidth="1.5"
            fill="none"
            opacity={lerp(0, 1, (p - 0.5) * 2)}
          />
        )}
      </svg>
      <div className="mt-8 text-center" style={{ opacity: textOpacity }}>
        <h2 className="text-2xl sm:text-4xl font-black text-white/90 tracking-tight">
          每位女性心裡
        </h2>
        <h2 className="text-2xl sm:text-4xl font-black text-[#f7c79a] tracking-tight mt-1">
          都住著一位仙女
        </h2>
      </div>
    </div>
  );
}

// ── Act 2: 萌芽 ──────────────────────────────────────────────
function SproutScene({ progress }: { progress: number }) {
  const p = ease(progress);
  const stemH = lerp(0, 25, p);
  const leafScale = lerp(0, 1, Math.max(0, (p - 0.4) / 0.6));
  const textOpacity = p < 0.15 ? p * 6 : p > 0.85 ? (1 - p) * 6 : 1;

  return (
    <div className="relative w-full h-full flex flex-col items-center justify-center px-8">
      <svg viewBox="0 0 100 100" className="w-40 h-40 sm:w-56 sm:h-56">
        {/* Soil */}
        <ellipse cx="50" cy="85" rx="35" ry="5" fill="rgba(139,105,20,0.3)" />
        {/* Stem growing up */}
        <path
          d={`M50 85 Q50 ${85 - stemH * 0.5} 50 ${85 - stemH}`}
          stroke="#7ab87a"
          strokeWidth={lerp(1.5, 3, p)}
          fill="none"
          strokeLinecap="round"
        />
        {/* Left leaf */}
        <ellipse
          cx={50 - lerp(0, 12, leafScale)}
          cy={85 - stemH + 5}
          rx={lerp(0, 8, leafScale)}
          ry={lerp(0, 4, leafScale)}
          fill="#8ccf8c"
          transform={`rotate(-30 ${50 - lerp(0, 12, leafScale)} ${85 - stemH + 5})`}
          opacity={leafScale}
        />
        {/* Right leaf */}
        <ellipse
          cx={50 + lerp(0, 12, leafScale)}
          cy={85 - stemH + 5}
          rx={lerp(0, 8, leafScale)}
          ry={lerp(0, 4, leafScale)}
          fill="#8ccf8c"
          transform={`rotate(30 ${50 + lerp(0, 12, leafScale)} ${85 - stemH + 5})`}
          opacity={leafScale}
        />
      </svg>
      <div className="mt-6 text-center" style={{ opacity: textOpacity }}>
        <h2 className="text-2xl sm:text-4xl font-black text-white/90 tracking-tight">
          遇見婕樂纖的那一天
        </h2>
        <p className="text-sm sm:text-lg text-white/50 mt-3 max-w-md">
          一顆種子悄悄種在心裡，對健康有了新的想像
        </p>
      </div>
    </div>
  );
}

// ── Act 3: 成長 ──────────────────────────────────────────────
function GrowScene({ progress }: { progress: number }) {
  const p = ease(progress);
  const bodyR = lerp(0, 14, p);
  const stemTop = lerp(60, 38, p);
  const leafRx = lerp(6, 12, p);
  const textOpacity = p < 0.15 ? p * 6 : p > 0.85 ? (1 - p) * 6 : 1;
  const faceOpacity = p > 0.4 ? Math.min(1, (p - 0.4) * 3) : 0;

  return (
    <div className="relative w-full h-full flex flex-col items-center justify-center px-8">
      <svg viewBox="0 0 100 100" className="w-48 h-48 sm:w-64 sm:h-64">
        <ellipse cx="50" cy="90" rx="22" ry="5" fill="rgba(0,0,0,0.08)" />
        <rect x="32" y="72" width="36" height="18" rx="3" fill="#b8826b" />
        <rect x="30" y="70" width="40" height="5" rx="2" fill="#9F6B3E" />
        {/* Stem */}
        <path d={`M50 72 Q50 ${lerp(72, stemTop, 0.5)} 50 ${stemTop}`} stroke="#7ab87a" strokeWidth="3" fill="none" strokeLinecap="round" />
        {/* Leaves */}
        <ellipse cx={50 - leafRx - 2} cy={stemTop + 12} rx={leafRx} ry={leafRx * 0.55} fill="#8ccf8c" transform={`rotate(-25 ${50 - leafRx - 2} ${stemTop + 12})`} />
        <ellipse cx={50 + leafRx + 2} cy={stemTop + 12} rx={leafRx} ry={leafRx * 0.55} fill="#8ccf8c" transform={`rotate(25 ${50 + leafRx + 2} ${stemTop + 12})`} />
        {/* Body */}
        {bodyR > 1 && <circle cx="50" cy={stemTop} r={bodyR} fill="#9edc9e" />}
        {/* Face */}
        <g opacity={faceOpacity}>
          <circle cx="46" cy={stemTop - 2} r="1.3" fill="#3d2e22" />
          <circle cx="54" cy={stemTop - 2} r="1.3" fill="#3d2e22" />
          <path d={`M47 ${stemTop + 3} Q50 ${stemTop + 5} 53 ${stemTop + 3}`} stroke="#3d2e22" strokeWidth="1.2" fill="none" strokeLinecap="round" />
        </g>
      </svg>
      <div className="mt-4 text-center" style={{ opacity: textOpacity }}>
        <h2 className="text-2xl sm:text-4xl font-black text-[#3d2e22] tracking-tight">
          找到最適合自己的方式
        </h2>
        <p className="text-sm sm:text-lg text-[#7a5836]/60 mt-3 max-w-md">
          三階梯定價、營養師陪伴，讓改變可以持續
        </p>
      </div>
    </div>
  );
}

// ── Act 4: 蛻變（花瓣展開）────────────────────────────────────
function TransformScene({ progress }: { progress: number }) {
  const p = ease(progress);
  const petalR = lerp(0, 30, p);
  const petalOpacity = Math.min(1, p * 2);
  const textOpacity = p < 0.15 ? p * 6 : p > 0.85 ? (1 - p) * 6 : 1;
  const bgPetalCount = Math.floor(lerp(0, 12, p));

  return (
    <div className="relative w-full h-full flex flex-col items-center justify-center px-8 overflow-hidden">
      {/* Background floating petals */}
      <div className="absolute inset-0 pointer-events-none">
        {Array.from({ length: bgPetalCount }).map((_, i) => {
          const x = (i * 37 + 13) % 100;
          const y = (i * 53 + 20) % 100;
          const rot = i * 47;
          const size = 15 + (i % 3) * 10;
          return (
            <svg
              key={i}
              className="absolute icon-float"
              style={{
                left: `${x}%`,
                top: `${y}%`,
                width: size,
                height: size,
                animationDelay: `${i * 0.3}s`,
                opacity: 0.15 + (i % 3) * 0.1,
              }}
              viewBox="0 0 20 20"
            >
              <ellipse cx="10" cy="10" rx="8" ry="5" fill="#ffc6d1" transform={`rotate(${rot} 10 10)`} />
            </svg>
          );
        })}
      </div>

      {/* Center flower unfurling */}
      <svg viewBox="0 0 100 100" className="w-56 h-56 sm:w-72 sm:h-72 relative z-10">
        {/* Petals expanding from center */}
        {[0, 72, 144, 216, 288].map((deg) => (
          <g key={deg} transform={`rotate(${deg} 50 50)`}>
            <ellipse
              cx="50"
              cy={50 - petalR}
              rx={lerp(0, 12, p)}
              ry={lerp(0, 8, p)}
              fill="url(#storyPetalGrad)"
              opacity={petalOpacity}
            />
          </g>
        ))}
        {/* Center */}
        <circle cx="50" cy="50" r={lerp(2, 8, p)} fill="#fff176" opacity={petalOpacity} />
        <circle cx="50" cy="50" r={lerp(1, 5, p)} fill="#ffee58" opacity={petalOpacity} />
        <defs>
          <linearGradient id="storyPetalGrad" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stopColor="#ffecf1" />
            <stop offset="100%" stopColor="#ff8fa8" />
          </linearGradient>
        </defs>
      </svg>
      <div className="mt-4 text-center relative z-10" style={{ opacity: textOpacity }}>
        <h2 className="text-2xl sm:text-4xl font-black text-white tracking-tight">
          改變正在發生
        </h2>
        <p className="text-sm sm:text-lg text-white/60 mt-3 max-w-md">
          一天一天，你開始看見自己的不同
        </p>
      </div>
    </div>
  );
}

// ── Act 5: 綻放（全螢幕花朵 + 金色光芒）──────────────────────
function BloomScene({ progress }: { progress: number }) {
  const p = ease(progress);
  const glowScale = lerp(0.5, 1.5, p);
  const textOpacity = p > 0.2 ? Math.min(1, (p - 0.2) * 2) : 0;
  const rayOpacity = lerp(0, 0.3, p);

  return (
    <div className="relative w-full h-full flex flex-col items-center justify-center px-8 overflow-hidden">
      {/* Radial gold rays */}
      <div
        className="absolute inset-0 pointer-events-none"
        style={{
          background: `radial-gradient(circle, rgba(252,213,97,${rayOpacity}) 0%, transparent 70%)`,
          transform: `scale(${glowScale})`,
        }}
      />

      {/* Full bloom flower — large */}
      <svg viewBox="0 0 100 100" className="w-64 h-64 sm:w-80 sm:h-80 relative z-10">
        {/* Outer glow */}
        <circle cx="50" cy="50" r={lerp(20, 45, p)} fill="rgba(255,205,210,0.15)" />

        {/* 5 large petals */}
        {[0, 72, 144, 216, 288].map((deg, i) => (
          <g key={deg} transform={`rotate(${deg} 50 50)`}>
            <path
              d={`M50 ${50 - lerp(8, 35, p)} C ${55 + lerp(0, 5, p)} ${50 - lerp(8, 35, p)}, ${58 + lerp(0, 5, p)} ${50 - lerp(4, 15, p)}, ${58 + lerp(0, 3, p)} 50 C ${58 + lerp(0, 3, p)} ${50 + lerp(4, 5, p)}, ${52} ${50 + lerp(2, 3, p)}, 50 50 C 48 ${50 + lerp(2, 3, p)}, ${42 - lerp(0, 3, p)} ${50 + lerp(4, 5, p)}, ${42 - lerp(0, 3, p)} 50 C ${42 - lerp(0, 5, p)} ${50 - lerp(4, 15, p)}, ${45 - lerp(0, 5, p)} ${50 - lerp(8, 35, p)}, 50 ${50 - lerp(8, 35, p)} Z`}
              fill={i % 2 === 0 ? '#ffc6d1' : '#ffb3c6'}
              opacity={lerp(0.5, 1, p)}
            />
          </g>
        ))}

        {/* Inner stamen */}
        <circle cx="50" cy="50" r={lerp(3, 8, p)} fill="#fff176" />
        <circle cx="50" cy="50" r={lerp(2, 5, p)} fill="#ffee58" />
        {[0, 60, 120, 180, 240, 300].map((a) => {
          const r = lerp(2, 5, p);
          const x = 50 + Math.cos((a * Math.PI) / 180) * r;
          const y = 50 + Math.sin((a * Math.PI) / 180) * r;
          return <circle key={a} cx={x} cy={y} r={lerp(0.3, 1, p)} fill="#f9a825" />;
        })}
      </svg>

      <div className="mt-6 text-center relative z-10" style={{ opacity: textOpacity }}>
        <h2 className="text-3xl sm:text-5xl font-black text-[#3d2e22] tracking-tight leading-tight">
          從仙女
          <br />
          <span className="text-[#9F6B3E]">到潘朵拉</span>
        </h2>
        <p className="text-sm sm:text-lg text-[#7a5836]/70 mt-4 max-w-lg mx-auto">
          花開了。你把這份美好分享給身邊的人。
        </p>
      </div>
    </div>
  );
}

// ── Assembled story ──────────────────────────────────────────
export default function AboutScrollStory() {
  const acts: Act[] = [
    { bg: ['#1a1410', '#1a2a1a'], render: (p) => <SeedScene progress={p} /> },
    { bg: ['#1a2a1a', '#2a3a2a'], render: (p) => <SproutScene progress={p} /> },
    { bg: ['#2a3a2a', '#fdf7ef'], render: (p) => <GrowScene progress={p} /> },
    { bg: ['#fdf7ef', '#f8bbd0'], render: (p) => <TransformScene progress={p} /> },
    { bg: ['#f8bbd0', '#fdf7ef'], render: (p) => <BloomScene progress={p} /> },
  ];

  return <ScrollStory acts={acts} />;
}
