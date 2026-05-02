'use client';

import type { MascotStage, MascotMood } from '@/lib/achievements';

interface MascotProps {
  stage?: MascotStage;
  mood?: MascotMood;
  size?: number;
  outfit?: string | null;
  backdrop?: string | null;
  className?: string;
}

/**
 * 寵物 — JEROSSE's mascot (placeholder until cross-app pet system lands).
 * Pure inline SVG (3 growth stages × 3 moods), CSS keyframes for life.
 */
export default function Mascot({
  stage = 'sprout',
  mood = 'neutral',
  size = 72,
  outfit = null,
  backdrop = null,
  className = '',
}: MascotProps) {
  return (
    <div
      className={`relative inline-block ${className}`}
      style={{ width: size, height: size }}
    >
      {backdrop && (
        <div className="absolute inset-0 rounded-full overflow-hidden mascot-backdrop">
          <Backdrop code={backdrop} />
        </div>
      )}
      <svg
        viewBox="0 0 100 100"
        className="relative w-full h-full mascot-bounce"
        aria-hidden="true"
      >
        {/* Pot */}
        <ellipse cx="50" cy="90" rx="22" ry="5" fill="#000" opacity="0.08" />
        <rect x="32" y="72" width="36" height="18" rx="3" fill="#b8826b" />
        <rect x="30" y="70" width="40" height="5" rx="2" fill="#9F6B3E" />

        {/* Stem + leaves by stage */}
        {stage === 'seedling' && (
          <>
            <path d="M50 72 Q50 62 50 55" stroke="#7ab87a" strokeWidth="3" fill="none" strokeLinecap="round" />
            <ellipse cx="44" cy="56" rx="6" ry="4" fill="#8ccf8c" transform="rotate(-25 44 56)" />
            <ellipse cx="56" cy="56" rx="6" ry="4" fill="#8ccf8c" transform="rotate(25 56 56)" />
          </>
        )}

        {stage === 'sprout' && (
          <>
            <path d="M50 72 Q50 55 50 42" stroke="#7ab87a" strokeWidth="3" fill="none" strokeLinecap="round" />
            <ellipse cx="40" cy="50" rx="9" ry="5" fill="#8ccf8c" transform="rotate(-25 40 50)" />
            <ellipse cx="60" cy="50" rx="9" ry="5" fill="#8ccf8c" transform="rotate(25 60 50)" />
            <circle cx="50" cy="35" r="14" fill="#9edc9e" />
          </>
        )}

        {stage === 'bloom' && (
          <>
            {/* Defs first — gradients, filters, glow */}
            <defs>
              <linearGradient id="petalGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stopColor="#ffecf1" />
                <stop offset="50%" stopColor="#ffc6d1" />
                <stop offset="100%" stopColor="#ff8fa8" />
              </linearGradient>
              <linearGradient id="petalGrad2" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stopColor="#ffe0e8" />
                <stop offset="100%" stopColor="#ffb3c6" />
              </linearGradient>
              <linearGradient id="leafGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stopColor="#a7e3a7" />
                <stop offset="100%" stopColor="#6faa6f" />
              </linearGradient>
              <radialGradient id="bloomGlow">
                <stop offset="0%" stopColor="#ffcdd2" stopOpacity="0.5" />
                <stop offset="100%" stopColor="#ffcdd2" stopOpacity="0" />
              </radialGradient>
              <filter id="petalShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur in="SourceAlpha" stdDeviation="1" />
                <feOffset dy="1.5" />
                <feComponentTransfer><feFuncA type="linear" slope="0.3" /></feComponentTransfer>
                <feMerge><feMergeNode /><feMergeNode in="SourceGraphic" /></feMerge>
              </filter>
            </defs>

            {/* Stem + vines — organic curves */}
            <path d="M50 72 Q49 56 50 38" stroke="#6fa86f" strokeWidth="3" fill="none" strokeLinecap="round" />
            <path d="M50 62 Q42 58 35 50" stroke="#7ab87a" strokeWidth="1.5" fill="none" strokeLinecap="round" className="bloom-leaf-l" />
            <path d="M50 62 Q58 58 65 50" stroke="#7ab87a" strokeWidth="1.5" fill="none" strokeLinecap="round" className="bloom-leaf-r" />

            {/* Leaves — animated gentle sway */}
            <ellipse cx="37" cy="51" rx="10" ry="5.5" fill="url(#leafGrad)" transform="rotate(-28 37 51)" className="bloom-leaf-l" />
            <ellipse cx="63" cy="51" rx="10" ry="5.5" fill="url(#leafGrad)" transform="rotate(28 63 51)" className="bloom-leaf-r" />
            {/* Tiny inner leaf */}
            <ellipse cx="42" cy="55" rx="5" ry="3" fill="#8ccf8c" opacity="0.7" transform="rotate(-15 42 55)" />
            <ellipse cx="58" cy="55" rx="5" ry="3" fill="#8ccf8c" opacity="0.7" transform="rotate(15 58 55)" />

            {/* Body — breathing scale */}
            <g className="bloom-body-breathe">
              <circle cx="50" cy="36" r="11" fill="#9edc9e" />
              <circle cx="46" cy="33" r="2.5" fill="#b8ecb8" opacity="0.6" />
              <circle cx="54" cy="38" r="1.5" fill="#b8ecb8" opacity="0.4" />
            </g>

            {/* Flower glow aura */}
            <circle cx="50" cy="22" r="18" fill="url(#bloomGlow)" className="bloom-aura" />

            {/* 5-petal cherry-blossom — each petal sways independently */}
            <g filter="url(#petalShadow)">
              {[0, 72, 144, 216, 288].map((deg, i) => (
                <g key={deg} transform={`rotate(${deg} 50 22)`} className={`bloom-petal bloom-petal-${i}`}>
                  <path
                    d="M50 11 C 54.5 11, 58 15.5, 58 20 C 58 23.5, 54 25, 50 25 C 46 25, 42 23.5, 42 20 C 42 15.5, 45.5 11, 50 11 Z"
                    fill={i % 2 === 0 ? 'url(#petalGrad)' : 'url(#petalGrad2)'}
                  />
                  {/* Petal vein */}
                  <path d={`M50 13 Q50 18 50 23`} stroke="#ffb3c6" strokeWidth="0.3" fill="none" opacity="0.5" />
                </g>
              ))}
              {/* Stamen cluster — richer */}
              <circle cx="50" cy="22" r="3.2" fill="#fff176" />
              <circle cx="50" cy="22" r="2" fill="#ffee58" />
              {[0, 60, 120, 180, 240, 300].map((a) => {
                const r = 1.8;
                const x = 50 + Math.cos((a * Math.PI) / 180) * r;
                const y = 22 + Math.sin((a * Math.PI) / 180) * r;
                return <circle key={a} cx={x} cy={y} r="0.6" fill="#f9a825" />;
              })}
            </g>

            {/* Falling petals — 2 tiny petals drifting down */}
            <g className="bloom-falling">
              <path d="M32 40 C 33 39, 35 40, 34 41 C 33 42, 31 41, 32 40 Z" fill="#ffc6d1" opacity="0.7" className="bloom-fall-1" />
              <path d="M66 35 C 67 34, 69 35, 68 36 C 67 37, 65 36, 66 35 Z" fill="#ffb3c6" opacity="0.5" className="bloom-fall-2" />
            </g>

            {/* Sparkles — more refined cross-stars */}
            <g fill="#fff9c4" className="mascot-sparkle">
              <path d="M28 16 l1 2.5 l2.5 1 l-2.5 1 l-1 2.5 l-1 -2.5 l-2.5 -1 l2.5 -1 z" />
              <path d="M72 24 l0.8 1.8 l1.8 0.8 l-1.8 0.8 l-0.8 1.8 l-0.8 -1.8 l-1.8 -0.8 l1.8 -0.8 z" />
              <path d="M24 36 l0.6 1.2 l1.2 0.6 l-1.2 0.6 l-0.6 1.2 l-0.6 -1.2 l-1.2 -0.6 l1.2 -0.6 z" />
              <path d="M76 14 l0.5 1 l1 0.5 l-1 0.5 l-0.5 1 l-0.5 -1 l-1 -0.5 l1 -0.5 z" />
            </g>
          </>
        )}

        {/* Face — always on the "head" (body) */}
        <Face mood={mood} stage={stage} />

        {/* Outfit overlay */}
        {outfit && <OutfitOverlay code={outfit} stage={stage} />}
      </svg>

      <style jsx>{`
        @keyframes mascotBounce {
          0%, 100% { transform: translateY(0); }
          50% { transform: translateY(-3px); }
        }
        .mascot-bounce { animation: mascotBounce 2.8s ease-in-out infinite; }

        /* Sparkles */
        @keyframes mascotSparkle {
          0%, 100% { opacity: 0.15; transform: scale(0.7); }
          50% { opacity: 1; transform: scale(1.15); }
        }
        :global(.mascot-sparkle path) {
          transform-origin: center; transform-box: fill-box;
          animation: mascotSparkle 2.4s ease-in-out infinite;
        }
        :global(.mascot-sparkle path:nth-child(2)) { animation-delay: 0.6s; }
        :global(.mascot-sparkle path:nth-child(3)) { animation-delay: 1.2s; }
        :global(.mascot-sparkle path:nth-child(4)) { animation-delay: 1.8s; }

        /* Bloom: petal sway — each rotates slightly around the flower center */
        @keyframes petalSway {
          0%, 100% { transform: rotate(0deg); }
          50% { transform: rotate(3deg); }
        }
        :global(.bloom-petal) {
          transform-origin: 50px 22px; /* flower center */
          animation: petalSway 4s ease-in-out infinite;
        }
        :global(.bloom-petal-1) { animation-delay: 0.3s; animation-duration: 4.5s; }
        :global(.bloom-petal-2) { animation-delay: 0.6s; animation-duration: 5s; }
        :global(.bloom-petal-3) { animation-delay: 0.9s; animation-duration: 4.2s; }
        :global(.bloom-petal-4) { animation-delay: 1.2s; animation-duration: 4.8s; }

        /* Bloom: body breathing */
        @keyframes bodyBreathe {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.04); }
        }
        :global(.bloom-body-breathe) {
          transform-origin: 50px 36px;
          animation: bodyBreathe 3.5s ease-in-out infinite;
        }

        /* Bloom: aura pulse */
        @keyframes auraPulse {
          0%, 100% { opacity: 0.3; transform: scale(0.9); }
          50% { opacity: 0.7; transform: scale(1.1); }
        }
        :global(.bloom-aura) {
          transform-origin: 50px 22px;
          animation: auraPulse 3s ease-in-out infinite;
        }

        /* Bloom: leaf sway */
        @keyframes leafSwayL {
          0%, 100% { transform: rotate(0deg); }
          50% { transform: rotate(-4deg); }
        }
        @keyframes leafSwayR {
          0%, 100% { transform: rotate(0deg); }
          50% { transform: rotate(4deg); }
        }
        :global(.bloom-leaf-l) { transform-origin: 50px 62px; animation: leafSwayL 5s ease-in-out infinite; }
        :global(.bloom-leaf-r) { transform-origin: 50px 62px; animation: leafSwayR 5s ease-in-out infinite 0.5s; }

        /* Bloom: falling petals */
        @keyframes petalFall1 {
          0% { transform: translate(0, 0) rotate(0deg); opacity: 0; }
          10% { opacity: 0.7; }
          100% { transform: translate(-12px, 30px) rotate(-60deg); opacity: 0; }
        }
        @keyframes petalFall2 {
          0% { transform: translate(0, 0) rotate(0deg); opacity: 0; }
          10% { opacity: 0.5; }
          100% { transform: translate(10px, 35px) rotate(45deg); opacity: 0; }
        }
        :global(.bloom-fall-1) { animation: petalFall1 6s ease-in-out infinite 1s; }
        :global(.bloom-fall-2) { animation: petalFall2 7s ease-in-out infinite 3s; }

        @media (prefers-reduced-motion: reduce) {
          .mascot-bounce,
          :global(.mascot-sparkle path),
          :global(.bloom-petal),
          :global(.bloom-body-breathe),
          :global(.bloom-aura),
          :global(.bloom-leaf-l),
          :global(.bloom-leaf-r),
          :global(.bloom-fall-1),
          :global(.bloom-fall-2) { animation: none; }
        }
      `}</style>
    </div>
  );
}

function Face({ mood, stage }: { mood: MascotMood; stage: MascotStage }) {
  // Face Y position shifts by stage
  const cy = stage === 'seedling' ? 56 : stage === 'sprout' ? 35 : 32;
  const eyeY = cy - 2;

  if (mood === 'excited') {
    return (
      <g>
        <path d={`M ${42} ${eyeY} L ${46} ${eyeY - 3} L ${50} ${eyeY}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
        <path d={`M ${50} ${eyeY} L ${54} ${eyeY - 3} L ${58} ${eyeY}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
        <path d={`M ${46} ${cy + 3} Q ${50} ${cy + 7} ${54} ${cy + 3}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
      </g>
    );
  }

  if (mood === 'happy') {
    return (
      <g>
        <path d={`M ${44} ${eyeY} Q ${46} ${eyeY - 2} ${48} ${eyeY}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
        <path d={`M ${52} ${eyeY} Q ${54} ${eyeY - 2} ${56} ${eyeY}`} stroke="#3d2e22" strokeWidth="1.5" fill="none" strokeLinecap="round" />
        <path d={`M ${46} ${cy + 3} Q ${50} ${cy + 6} ${54} ${cy + 3}`} stroke="#3d2e22" strokeWidth="1.2" fill="none" strokeLinecap="round" />
      </g>
    );
  }

  // neutral
  return (
    <g>
      <circle cx="46" cy={eyeY} r="1.3" fill="#3d2e22" />
      <circle cx="54" cy={eyeY} r="1.3" fill="#3d2e22" />
      <path d={`M ${47} ${cy + 4} Q ${50} ${cy + 5.5} ${53} ${cy + 4}`} stroke="#3d2e22" strokeWidth="1.2" fill="none" strokeLinecap="round" />
    </g>
  );
}

function OutfitOverlay({ code, stage }: { code: string; stage: MascotStage }) {
  const y = stage === 'seedling' ? 40 : stage === 'sprout' ? 18 : 14;

  const ey = stage === 'seedling' ? 58 : stage === 'sprout' ? 37 : 34;
  const sy = stage === 'bloom' ? 50 : stage === 'sprout' ? 55 : 65;

  switch (code) {
    case 'acorn_hat':
      return (
        <g transform={`translate(50, ${y + 4})`}>
          <ellipse rx="6" ry="3" fill="#8B5E3C" />
          <circle cy="-3" r="4.5" fill="#A0522D" />
          <rect x="-1.5" y="-7" width="3" height="3" rx="1" fill="#5D4037" />
        </g>
      );
    case 'ribbon':
      return (
        <g transform={`translate(50, ${y + 4})`}>
          <path d="M-5 0L0-4L5 0L0 2Z" fill="#E0748C" />
          <path d="M-6 1L0 4L6 1" stroke="#E0748C" strokeWidth="1.5" fill="none" />
          <circle r="1.5" fill="#d4627a" />
        </g>
      );
    case 'beret':
      return (
        <g transform={`translate(50, ${y + 3})`}>
          <ellipse rx="8" ry="3" fill="#5D4037" />
          <ellipse cy="-2" rx="6.5" ry="4" fill="#6D4C41" />
          <circle cy="-5" r="1" fill="#4E342E" />
        </g>
      );
    case 'flower_crown':
      return (
        <g transform={`translate(50, ${y + 2})`}>
          {[-6, -2, 2, 6].map((x) => (
            <circle key={x} cx={x} r="2.5" fill="#F7A8B8" opacity="0.8" />
          ))}
          {[-4, 0, 4].map((x) => (
            <circle key={x} cx={x} cy="-1" r="1" fill="#FCE374" opacity="0.7" />
          ))}
        </g>
      );
    case 'star_halo':
      return (
        <g transform={`translate(50, ${y})`}>
          <path d="M0-7L1.2-1.2L7 0L1.2 1.2L0 7L-1.2 1.2L-7 0L-1.2-1.2Z" fill="#FCE374" opacity="0.7" />
          <circle cx="-5" cy="-4" r="0.8" fill="#FCE374" opacity="0.5" />
          <circle cx="5" cy="-3" r="0.6" fill="#FCE374" opacity="0.5" />
        </g>
      );
    case 'glasses':
      return (
        <g transform={`translate(50, ${ey - 2})`}>
          <circle cx="-4" r="3" stroke="#5D4037" strokeWidth="0.8" fill="none" />
          <circle cx="4" r="3" stroke="#5D4037" strokeWidth="0.8" fill="none" />
          <path d="M-1 0h2" stroke="#5D4037" strokeWidth="0.6" />
          <path d="M-7 -1h0M7 -1h0" stroke="#5D4037" strokeWidth="0.6" />
        </g>
      );
    case 'sunglasses':
      return (
        <g transform={`translate(50, ${ey - 2})`}>
          <path d="M-7-1C-7-1-6-2-4-2S-1-1-1-1" stroke="#333" strokeWidth="1" fill="#333" opacity="0.7" />
          <path d="M1-1C1-1 2-2 4-2S7-1 7-1" stroke="#333" strokeWidth="1" fill="#333" opacity="0.7" />
          <path d="M-1 -1h2" stroke="#333" strokeWidth="0.8" />
        </g>
      );
    case 'heart_eyes':
      return (
        <g transform={`translate(50, ${ey - 1})`}>
          <path d="M-6 0c0-2 1.5-3 3-1.5 1.5-1.5 3 -.5 3 1.5 0 2.5-3 4-3 4s-3-1.5-3-4z" fill="#E0748C" />
          <path d="M3 0c0-2 1.5-3 3-1.5 1.5-1.5 3 -.5 3 1.5 0 2.5-3 4-3 4s-3-1.5-3-4z" fill="#E0748C" />
        </g>
      );
    case 'scarf':
      return (
        <g transform={`translate(50, ${sy})`}>
          <path d="M-8 0C-8-2 8-2 8 0" stroke="#C62828" strokeWidth="2.5" fill="none" strokeLinecap="round" />
          <path d="M6 0v5" stroke="#C62828" strokeWidth="2" strokeLinecap="round" />
          <path d="M4 0v4" stroke="#C62828" strokeWidth="1.5" strokeLinecap="round" opacity="0.7" />
        </g>
      );
    case 'pearl':
      return (
        <g transform={`translate(50, ${sy})`}>
          {[-5, -2, 1, 4].map((x) => (
            <circle key={x} cx={x} r="1.8" fill="#e8e0d8" stroke="#d4c8bc" strokeWidth="0.4" />
          ))}
        </g>
      );
    case 'friendship_charm':
      // Gold chain across the neck with a pink + gold heart pendant.
      // Heart-beat pulse via inline animateTransform — runs only when the
      // mascot wears it, so we don't pay for the animation otherwise.
      return (
        <g transform={`translate(50, ${sy})`}>
          {/* Chain — slight U */}
          <path d="M-7 -1q7 4 14 0" stroke="#E8C76B" strokeWidth="0.8" fill="none" strokeLinecap="round" />
          <path d="M-7 -1q7 4 14 0" stroke="#FFE08A" strokeWidth="0.3" fill="none" strokeLinecap="round" />
          {/* Twin hearts — pink left, gold right, gently overlap */}
          <g transform="translate(-1.5 3)">
            <path d="M0 0c-1-1.2-3-0.7-3 0.8 0 1.5 1.7 2.7 3 3.6 1.3-0.9 3-2.1 3-3.6 0-1.5-2-2-3-0.8z" fill="#F27BA7" />
            <animateTransform attributeName="transform" type="translate" values="-1.5 3; -1.5 2.6; -1.5 3" dur="1.4s" repeatCount="indefinite" additive="sum" />
          </g>
          <g transform="translate(2.5 3.5)">
            <path d="M0 0c-1-1.2-3-0.7-3 0.8 0 1.5 1.7 2.7 3 3.6 1.3-0.9 3-2.1 3-3.6 0-1.5-2-2-3-0.8z" fill="#F6B94E" />
            <animateTransform attributeName="transform" type="translate" values="2.5 3.5; 2.5 3.1; 2.5 3.5" dur="1.4s" begin="0.2s" repeatCount="indefinite" additive="sum" />
          </g>
          {/* Tiny sparkle */}
          <circle cx="6" cy="0" r="0.4" fill="#FFE08A">
            <animate attributeName="opacity" values="0;1;0" dur="2s" repeatCount="indefinite" />
          </circle>
        </g>
      );
    default:
      return null;
  }
}

function Backdrop({ code }: { code: string }) {
  const GRADIENTS: Record<string, string> = {
    meadow: 'linear-gradient(180deg, #c8e6c9 0%, #e8f5e9 100%)',
    garden: 'linear-gradient(180deg, #f8bbd0 0%, #fce4ec 100%)',
    sakura: 'linear-gradient(180deg, #f48fb1 0%, #fff0f5 100%)',
    starry: 'linear-gradient(180deg, #1a237e 0%, #3949ab 100%)',
    rainbow: 'linear-gradient(180deg, #ff9a9e 0%, #fad0c4 50%, #a1c4fd 100%)',
    beach: 'linear-gradient(180deg, #80deea 0%, #fff9c4 100%)',
    // Sunset picnic vibe — referral-only backdrop. Soft pink → coral → lavender
    // sky with floating heart confetti drifting upward.
    friend_picnic: 'linear-gradient(180deg, #fcd8e5 0%, #f8b5c8 55%, #c56c97 100%)',
  };
  const isFriendPicnic = code === 'friend_picnic';
  return (
    <div className="w-full h-full relative overflow-hidden" style={{ background: GRADIENTS[code] || GRADIENTS.meadow }}>
      {isFriendPicnic && (
        <svg className="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 100 100" aria-hidden>
          {/* Drifting heart confetti — staggered fall + fade loop */}
          {[
            { x: 12, delay: 0, op: 0.7 },
            { x: 30, delay: 1.3, op: 0.5 },
            { x: 55, delay: 0.6, op: 0.6 },
            { x: 75, delay: 2.0, op: 0.8 },
            { x: 88, delay: 0.9, op: 0.5 },
          ].map((h, i) => (
            <g key={i} transform={`translate(${h.x} 100)`} opacity={h.op}>
              <path d="M0 0c-1.5-1.8-4-1-4 1.2 0 2 2.5 3.6 4 4.8 1.5-1.2 4-2.8 4-4.8 0-2.2-2.5-3-4-1.2z" fill="#fff" />
              <animateTransform attributeName="transform" type="translate" values={`${h.x} 100; ${h.x} -10`} dur="9s" begin={`${h.delay}s`} repeatCount="indefinite" />
              <animate attributeName="opacity" values={`0;${h.op};${h.op};0`} dur="9s" begin={`${h.delay}s`} repeatCount="indefinite" />
            </g>
          ))}
          {/* Soft sun */}
          <circle cx="80" cy="22" r="9" fill="#FFE7B3" opacity="0.7" />
        </svg>
      )}
    </div>
  );
}
