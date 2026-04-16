'use client';

/**
 * 婕樂纖仙女館 brand logo — colorful version.
 *   left leaf: 健康 (emerald green)
 *   right leaf: 美容 (coral / rose)
 *   sparkle: 仙女 (warm gold)
 *   ring: warm cream halo
 */

interface Props {
  size?: number;
  className?: string;
  variant?: 'full' | 'mark' | 'wordmark';
  textClassName?: string;
}

export default function Logo({
  size = 36,
  className = '',
  variant = 'full',
  textClassName = '',
}: Props) {
  const mark = (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      className="shrink-0"
    >
      <defs>
        <linearGradient id="leafGreen" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#7BC47F" />
          <stop offset="100%" stopColor="#4A9D5F" />
        </linearGradient>
        <linearGradient id="leafPink" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#F7A8B8" />
          <stop offset="100%" stopColor="#E0748C" />
        </linearGradient>
        <radialGradient id="sparkleGold" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#FCE374" />
          <stop offset="100%" stopColor="#E8A93B" />
        </radialGradient>
        <linearGradient id="ringWarm" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="#fff7eb" />
          <stop offset="100%" stopColor="#f7eee3" />
        </linearGradient>
      </defs>

      {/* Outer warm cream ring */}
      <circle cx="32" cy="32" r="30" fill="url(#ringWarm)" stroke="#e7d9cb" strokeWidth="1.5" />

      {/* Left leaf — health green */}
      <path
        d="M32 48 C 22 44, 16 36, 16 26 C 22 28, 28 32, 32 38 Z"
        fill="url(#leafGreen)"
      />
      {/* Right leaf — beauty pink */}
      <path
        d="M32 48 C 42 44, 48 36, 48 26 C 42 28, 36 32, 32 38 Z"
        fill="url(#leafPink)"
      />
      {/* Leaf mid-veins */}
      <path
        d="M32 38 C 28 33, 23 30, 19 28"
        stroke="#2f6b3e"
        strokeWidth="0.8"
        strokeLinecap="round"
        fill="none"
        opacity="0.45"
      />
      <path
        d="M32 38 C 36 33, 41 30, 45 28"
        stroke="#9c3e55"
        strokeWidth="0.8"
        strokeLinecap="round"
        fill="none"
        opacity="0.45"
      />

      {/* Central warm stem dot */}
      <circle cx="32" cy="48" r="2.4" fill="#9F6B3E" />

      {/* Top sparkle — gold */}
      <g transform="translate(32 17)">
        <path
          d="M0 -8 L1.2 -1.2 L8 0 L1.2 1.2 L0 8 L-1.2 1.2 L-8 0 L-1.2 -1.2 Z"
          fill="url(#sparkleGold)"
        />
      </g>

      {/* Accent sparkles */}
      <circle cx="48" cy="18" r="1.3" fill="#FCE374" opacity="0.9" />
      <circle cx="18" cy="22" r="1" fill="#F7A8B8" opacity="0.85" />
      <circle cx="50" cy="44" r="0.9" fill="#7BC47F" opacity="0.7" />
    </svg>
  );

  if (variant === 'mark') {
    return <div className={className}>{mark}</div>;
  }

  const wordmark = (
    <span
      className={`font-black text-[#1f1a15] tracking-tight leading-none ${textClassName}`}
      style={{ fontFamily: '"Microsoft JhengHei", "微軟正黑體", "Noto Sans TC", sans-serif' }}
    >
      婕樂纖<span className="text-[#9F6B3E]">仙女館</span>
    </span>
  );

  if (variant === 'wordmark') {
    return <div className={className}>{wordmark}</div>;
  }

  return (
    <div className={`flex items-center gap-2 ${className}`}>
      {mark}
      <div className="flex flex-col leading-tight">
        {wordmark}
        <span
          className="text-[9px] font-bold tracking-[0.2em] text-[#7a5836] mt-0.5"
          style={{ fontFamily: '"Inter", "Helvetica", sans-serif' }}
        >
          FAIRY PANDORA
        </span>
      </div>
    </div>
  );
}
