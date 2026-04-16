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
 * 芽芽 — JEROSSE's sprout mascot.
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
            <path d="M50 72 Q50 52 50 38" stroke="#7ab87a" strokeWidth="3" fill="none" strokeLinecap="round" />
            <ellipse cx="38" cy="50" rx="10" ry="6" fill="#8ccf8c" transform="rotate(-25 38 50)" />
            <ellipse cx="62" cy="50" rx="10" ry="6" fill="#8ccf8c" transform="rotate(25 62 50)" />
            {/* Flower petals */}
            <circle cx="50" cy="25" r="8" fill="#ffb7c5" />
            <circle cx="40" cy="30" r="8" fill="#ffc6d1" />
            <circle cx="60" cy="30" r="8" fill="#ffc6d1" />
            <circle cx="45" cy="20" r="7" fill="#ffd1dc" />
            <circle cx="55" cy="20" r="7" fill="#ffd1dc" />
            <circle cx="50" cy="28" r="5" fill="#fff176" />
            <circle cx="50" cy="32" r="10" fill="#9edc9e" />
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
        .mascot-bounce {
          animation: mascotBounce 2.8s ease-in-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
          .mascot-bounce { animation: none; }
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

  switch (code) {
    case 'acorn_hat':
      return <text x="50" y={y + 8} textAnchor="middle" fontSize="14">🌰</text>;
    case 'ribbon':
      return <text x="50" y={y + 8} textAnchor="middle" fontSize="14">🎀</text>;
    case 'beret':
      return <text x="50" y={y + 8} textAnchor="middle" fontSize="14">🎩</text>;
    case 'flower_crown':
      return <text x="50" y={y + 8} textAnchor="middle" fontSize="16">🌸</text>;
    case 'star_halo':
      return <text x="50" y={y + 6} textAnchor="middle" fontSize="16">✨</text>;
    case 'glasses':
      return <text x="50" y={(stage === 'seedling' ? 58 : stage === 'sprout' ? 37 : 34)} textAnchor="middle" fontSize="10">👓</text>;
    case 'sunglasses':
      return <text x="50" y={(stage === 'seedling' ? 58 : stage === 'sprout' ? 37 : 34)} textAnchor="middle" fontSize="10">🕶️</text>;
    case 'heart_eyes':
      return <text x="50" y={(stage === 'seedling' ? 58 : stage === 'sprout' ? 37 : 34)} textAnchor="middle" fontSize="10">😍</text>;
    case 'scarf':
      return <text x="50" y={stage === 'bloom' ? 50 : stage === 'sprout' ? 55 : 65} textAnchor="middle" fontSize="12">🧣</text>;
    case 'pearl':
      return <text x="50" y={stage === 'bloom' ? 50 : stage === 'sprout' ? 55 : 65} textAnchor="middle" fontSize="10">🫧</text>;
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
  };
  return <div className="w-full h-full" style={{ background: GRADIENTS[code] || GRADIENTS.meadow }} />;
}
