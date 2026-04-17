'use client';

/**
 * Scroll-driven mascot growth animation.
 *
 * As the user scrolls through the container, the mascot SVG morphs
 * continuously from seedling → sprout → bloom. Not a div swap —
 * the actual SVG paths interpolate: stem grows, leaves expand,
 * petals unfurl, face moves up.
 *
 * Uses Intersection Observer + scroll position to compute a 0→1
 * progress value, then interpolates all SVG attributes.
 */

import { useEffect, useRef, useState } from 'react';

interface Props {
  className?: string;
  size?: number;
}

export default function ScrollMascotGrowth({ className = '', size = 200 }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [progress, setProgress] = useState(0); // 0 = seedling, 0.5 = sprout, 1 = bloom

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;

    const onScroll = () => {
      const rect = el.getBoundingClientRect();
      const viewH = window.innerHeight;
      // Start when top of container enters bottom 80% of viewport
      // End when bottom of container reaches top 20%
      const start = viewH * 0.8;
      const end = viewH * 0.15;
      const total = start - end;
      const current = start - rect.top;
      const p = Math.max(0, Math.min(1, current / total));
      setProgress(p);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll(); // initial
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <div ref={containerRef} className={`flex justify-center ${className}`}>
      <MorphingSvg progress={progress} size={size} />
    </div>
  );
}

function MorphingSvg({ progress, size }: { progress: number; size: number }) {
  // Lerp helper
  const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

  // === Stage-based interpolation ===
  // 0.0–0.35 = seedling→sprout transition
  // 0.35–0.65 = sprout stable
  // 0.65–1.0 = sprout→bloom transition

  const seedToSprout = Math.max(0, Math.min(1, progress / 0.35));
  const sproutToBloom = Math.max(0, Math.min(1, (progress - 0.65) / 0.35));

  // Stem height: 10 → 30 → 34
  const stemTop = lerp(65, 42, seedToSprout) - sproutToBloom * 4;

  // Body (head) radius: 0 → 14 → 11 (shrinks for bloom to make room for flower)
  const bodyR = seedToSprout > 0.3 ? lerp(0, 14, Math.min(1, (seedToSprout - 0.3) / 0.7)) : 0;
  const bodyRFinal = bodyR - sproutToBloom * 3;
  const bodyCy = lerp(56, 35, seedToSprout) - sproutToBloom * 3;

  // Leaves: scale from 0 → 1
  const leafScale = Math.min(1, seedToSprout * 1.5);
  const leafRx = lerp(0, 9, leafScale) + sproutToBloom * 1;
  const leafRy = lerp(0, 5, leafScale) + sproutToBloom * 1;
  const leafLx = lerp(50, 40, leafScale) - sproutToBloom * 2;
  const leafRxPos = lerp(50, 60, leafScale) + sproutToBloom * 2;
  const leafY = lerp(60, 50, seedToSprout);

  // Face position
  const faceCy = bodyCy;
  const faceOpacity = seedToSprout > 0.5 ? Math.min(1, (seedToSprout - 0.5) * 4) : 0;

  // Flower petals (bloom only)
  const petalScale = sproutToBloom;
  const petalOpacity = sproutToBloom;
  const flowerCy = bodyCy - bodyRFinal - 6;

  // Sparkles (bloom only)
  const sparkleOpacity = Math.max(0, (sproutToBloom - 0.5) * 2);

  // Pot
  const potY = 72;

  return (
    <svg
      viewBox="0 0 100 100"
      style={{ width: size, height: size }}
      className="transition-none"
    >
      {/* Pot — always visible */}
      <ellipse cx="50" cy="90" rx="22" ry="5" fill="#000" opacity="0.08" />
      <rect x="32" y={potY} width="36" height="18" rx="3" fill="#b8826b" />
      <rect x="30" y="70" width="40" height="5" rx="2" fill="#9F6B3E" />

      {/* Stem — grows upward */}
      <path
        d={`M50 ${potY} Q50 ${lerp(potY, stemTop, 0.5)} 50 ${stemTop}`}
        stroke="#7ab87a"
        strokeWidth={lerp(2, 3.2, seedToSprout)}
        fill="none"
        strokeLinecap="round"
      />

      {/* Side vines (appear as we approach sprout) */}
      {leafScale > 0.1 && (
        <>
          <path
            d={`M50 ${leafY + 8} Q${leafLx + 5} ${leafY + 5} ${leafLx} ${leafY}`}
            stroke="#7ab87a"
            strokeWidth={lerp(0, 1.5, leafScale)}
            fill="none"
            strokeLinecap="round"
            opacity={leafScale}
          />
          <path
            d={`M50 ${leafY + 8} Q${leafRxPos - 5} ${leafY + 5} ${leafRxPos} ${leafY}`}
            stroke="#7ab87a"
            strokeWidth={lerp(0, 1.5, leafScale)}
            fill="none"
            strokeLinecap="round"
            opacity={leafScale}
          />
        </>
      )}

      {/* Leaves — expand outward */}
      {leafScale > 0.1 && (
        <>
          <ellipse
            cx={leafLx}
            cy={leafY}
            rx={leafRx}
            ry={leafRy}
            fill="#8ccf8c"
            transform={`rotate(-25 ${leafLx} ${leafY})`}
            opacity={leafScale}
          />
          <ellipse
            cx={leafRxPos}
            cy={leafY}
            rx={leafRx}
            ry={leafRy}
            fill="#8ccf8c"
            transform={`rotate(25 ${leafRxPos} ${leafY})`}
            opacity={leafScale}
          />
        </>
      )}

      {/* Body (green ball) — fades in during sprout phase */}
      {bodyRFinal > 0 && (
        <circle cx="50" cy={bodyCy} r={Math.max(0, bodyRFinal)} fill="#9edc9e" />
      )}

      {/* Flower petals — unfurl during bloom */}
      {petalScale > 0.01 && (
        <g opacity={petalOpacity}>
          <defs>
            <linearGradient id="scrollPetalGrad" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#ffecf1" />
              <stop offset="100%" stopColor="#ff8fa8" />
            </linearGradient>
          </defs>
          {[0, 72, 144, 216, 288].map((deg) => (
            <g
              key={deg}
              transform={`rotate(${deg} 50 ${flowerCy})`}
              style={{
                transformOrigin: `50px ${flowerCy}px`,
              }}
            >
              <ellipse
                cx="50"
                cy={flowerCy - lerp(0, 10, petalScale)}
                rx={lerp(0, 7, petalScale)}
                ry={lerp(0, 5, petalScale)}
                fill="url(#scrollPetalGrad)"
              />
            </g>
          ))}
          {/* Stamen center */}
          <circle
            cx="50"
            cy={flowerCy}
            r={lerp(0, 3, petalScale)}
            fill="#fff176"
          />
        </g>
      )}

      {/* Face — fades in during sprout */}
      <g opacity={faceOpacity}>
        {/* Eyes */}
        {sproutToBloom > 0.5 ? (
          // Excited face for bloom
          <>
            <path d={`M ${44} ${faceCy - 2} Q ${46} ${faceCy - 4} ${48} ${faceCy - 2}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
            <path d={`M ${52} ${faceCy - 2} Q ${54} ${faceCy - 4} ${56} ${faceCy - 2}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
            <path d={`M ${46} ${faceCy + 3} Q ${50} ${faceCy + 7} ${54} ${faceCy + 3}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
          </>
        ) : (
          // Neutral → happy face
          <>
            <circle cx="46" cy={faceCy - 2} r="1.3" fill="#3d2e22" />
            <circle cx="54" cy={faceCy - 2} r="1.3" fill="#3d2e22" />
            <path d={`M ${47} ${faceCy + 4} Q ${50} ${faceCy + lerp(5, 6, seedToSprout)} ${53} ${faceCy + 4}`} stroke="#3d2e22" strokeWidth="1.2" fill="none" strokeLinecap="round" />
          </>
        )}
      </g>

      {/* Sparkles — bloom only */}
      {sparkleOpacity > 0 && (
        <g fill="#fff9c4" opacity={sparkleOpacity}>
          <path d="M28 20 l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 l2.5 -1 z" className="icon-pulse" />
          <path d="M72 28 l0.8 1.8 l1.8 0.8 l-1.8 0.8 l-0.8 1.8 l-0.8 -1.8 l-1.8 -0.8 l1.8 -0.8 z" className="icon-pulse" style={{ animationDelay: '0.6s' }} />
          <path d="M24 40 l0.6 1.2 l1.2 0.6 l-1.2 0.6 l-0.6 1.2 l-0.6 -1.2 l-1.2 -0.6 l1.2 -0.6 z" className="icon-pulse" style={{ animationDelay: '1.2s' }} />
        </g>
      )}

      {/* Progress label */}
      <text
        x="50"
        y="98"
        textAnchor="middle"
        fontSize="5"
        fontWeight="bold"
        fill="#9F6B3E"
        opacity="0.6"
      >
        {progress < 0.35 ? '萌芽中…' : progress < 0.65 ? '成長中…' : progress < 0.95 ? '即將綻放…' : '綻放！'}
      </text>
    </svg>
  );
}
