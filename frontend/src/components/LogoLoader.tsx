'use client';

/**
 * Branded loading indicator — animated logo (rotating ring, breathing leaves, sparkles).
 * Replaces traditional spinners everywhere.
 *
 *   <LogoLoader size={80} />
 *   <LogoLoader size={48} label="載入中…" />
 */

interface Props {
  size?: number;
  label?: string;
  className?: string;
  variant?: 'inline' | 'overlay';
}

export default function LogoLoader({
  size = 72,
  label,
  className = '',
  variant = 'inline',
}: Props) {
  const mark = (
    <div
      className={`logo-loader ${className}`}
      style={{ width: size, height: size }}
      aria-label="載入中"
      role="status"
    >
      <svg width={size} height={size} viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="ll-g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#7BC47F" />
            <stop offset="1" stopColor="#4A9D5F" />
          </linearGradient>
          <linearGradient id="ll-p" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#F7A8B8" />
            <stop offset="1" stopColor="#E0748C" />
          </linearGradient>
          <radialGradient id="ll-s">
            <stop offset="0" stopColor="#FCE374" />
            <stop offset="1" stopColor="#E8A93B" />
          </radialGradient>
          {/* Spinning arc */}
          <linearGradient id="ll-arc" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stopColor="#9F6B3E" stopOpacity="0" />
            <stop offset="0.5" stopColor="#9F6B3E" stopOpacity="0.5" />
            <stop offset="1" stopColor="#9F6B3E" stopOpacity="1" />
          </linearGradient>
        </defs>

        {/* Outer halo ring */}
        <circle cx="32" cy="32" r="30" fill="#fff7eb" stroke="#e7d9cb" strokeWidth="1.5" />

        {/* Spinning arc — gives loading feel */}
        <g className="ll-spin" style={{ transformOrigin: '32px 32px' }}>
          <circle
            cx="32"
            cy="32"
            r="28"
            fill="none"
            stroke="url(#ll-arc)"
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeDasharray="40 200"
          />
        </g>

        {/* Left leaf — breathe */}
        <g className="ll-leaf-l" style={{ transformOrigin: '32px 38px' }}>
          <path
            d="M32 48 C 22 44, 16 36, 16 26 C 22 28, 28 32, 32 38 Z"
            fill="url(#ll-g)"
          />
        </g>
        {/* Right leaf — breathe (delayed) */}
        <g className="ll-leaf-r" style={{ transformOrigin: '32px 38px' }}>
          <path
            d="M32 48 C 42 44, 48 36, 48 26 C 42 28, 36 32, 32 38 Z"
            fill="url(#ll-p)"
          />
        </g>

        <circle cx="32" cy="48" r="2.4" fill="#9F6B3E" />

        {/* Top sparkle — pulse */}
        <g className="ll-sparkle" style={{ transformOrigin: '32px 17px' }}>
          <path
            transform="translate(32 17)"
            d="M0 -8 L1.2 -1.2 L8 0 L1.2 1.2 L0 8 L-1.2 1.2 L-8 0 L-1.2 -1.2 Z"
            fill="url(#ll-s)"
          />
        </g>

        {/* Accent sparkles — twinkle */}
        <circle cx="48" cy="18" r="1.3" fill="#FCE374" className="ll-twinkle" style={{ animationDelay: '0.2s' }} />
        <circle cx="18" cy="22" r="1" fill="#F7A8B8" className="ll-twinkle" style={{ animationDelay: '0.6s' }} />
        <circle cx="50" cy="44" r="0.9" fill="#7BC47F" className="ll-twinkle" style={{ animationDelay: '1s' }} />
      </svg>

      <style jsx>{`
        .logo-loader { display: inline-block; position: relative; }
        @keyframes ll-spin { to { transform: rotate(360deg); } }
        @keyframes ll-breathe-l {
          0%, 100% { transform: scale(1) rotate(0deg); }
          50% { transform: scale(1.08) rotate(-2deg); }
        }
        @keyframes ll-breathe-r {
          0%, 100% { transform: scale(1) rotate(0deg); }
          50% { transform: scale(1.08) rotate(2deg); }
        }
        @keyframes ll-pulse {
          0%, 100% { transform: scale(1); opacity: 1; }
          50% { transform: scale(1.3); opacity: 0.85; }
        }
        @keyframes ll-twinkle {
          0%, 100% { opacity: 0.3; transform: scale(0.7); }
          50% { opacity: 1; transform: scale(1.4); }
        }
        :global(.ll-spin) { animation: ll-spin 2.2s linear infinite; }
        :global(.ll-leaf-l) { animation: ll-breathe-l 2s ease-in-out infinite; }
        :global(.ll-leaf-r) { animation: ll-breathe-r 2s ease-in-out infinite 0.3s; }
        :global(.ll-sparkle) { animation: ll-pulse 1.6s ease-in-out infinite; }
        :global(.ll-twinkle) {
          transform-box: fill-box;
          transform-origin: center;
          animation: ll-twinkle 1.8s ease-in-out infinite;
        }
        @media (prefers-reduced-motion: reduce) {
          :global(.ll-spin), :global(.ll-leaf-l), :global(.ll-leaf-r),
          :global(.ll-sparkle), :global(.ll-twinkle) { animation: none; }
        }
      `}</style>
    </div>
  );

  if (variant === 'overlay') {
    return (
      <div
        className="fixed inset-0 z-[400] bg-white/90 backdrop-blur-sm flex flex-col items-center justify-center gap-4"
        role="status"
        aria-live="polite"
      >
        {mark}
        {label && (
          <p className="text-sm font-black text-[#9F6B3E] tracking-wide">{label}</p>
        )}
      </div>
    );
  }

  return (
    <div className="inline-flex flex-col items-center gap-3">
      {mark}
      {label && <p className="text-xs font-bold text-[#9F6B3E] tracking-wide">{label}</p>}
    </div>
  );
}
