/**
 * Colorful + animated icons for mascot outfits, backdrops, achievements.
 * Used in 寵物之家 to make the grid feel alive (vs. monochrome SiteIcon).
 *
 * Falls back to SiteIcon if the code isn't defined here.
 */

import SiteIcon from './SiteIcon';

interface Props {
  name: string;
  size?: number;
  className?: string;
  /** Disable idle animation (e.g. locked items) */
  static?: boolean;
}

export default function OutfitIcon({ name, size = 32, className = '', static: isStatic = false }: Props) {
  const s = { width: size, height: size } as React.CSSProperties;
  const cls = `inline-block shrink-0 ${className} ${isStatic ? '' : 'outfit-icon-anim'}`;
  const anim = (key: string) => (isStatic ? undefined : key);

  switch (name) {
    // ── Outfits: head ───────────────────────────────────────
    case 'acorn-hat':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('bob')}>
          <ellipse cx="20" cy="28" rx="11" ry="3" fill="#000" opacity="0.08" />
          <path d="M8 20a12 8 0 0 1 24 0q0 2-12 2t-12-2z" fill="#8B5A2B" />
          <path d="M8 20a12 8 0 0 1 24 0q0 2-12 2t-12-2z" fill="url(#acorn-shine)" />
          <rect x="18" y="6" width="4" height="7" rx="1.5" fill="#5A3A1F" />
          <ellipse cx="20" cy="6.5" rx="2" ry="1.2" fill="#3D2915" />
          <defs>
            <linearGradient id="acorn-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.4" />
              <stop offset="0.5" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'ribbon-bow':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('pulse')}>
          <path d="M20 20L8 12c-2-2-2-6 2-6s6 2 10 8" fill="#F27BA7" />
          <path d="M20 20l12-8c2-2 2-6-2-6s-6 2-10 8" fill="#F27BA7" />
          <path d="M20 20L8 12c-2-2-2-6 2-6s6 2 10 8" fill="url(#bow-shine-l)" />
          <path d="M20 20l12-8c2-2 2-6-2-6s-6 2-10 8" fill="url(#bow-shine-r)" />
          <path d="M20 20l-6 10c-1 2 0 4 2 3l4-5z" fill="#D65689" />
          <path d="M20 20l6 10c1 2 0 4-2 3l-4-5z" fill="#D65689" />
          <circle cx="20" cy="20" r="3" fill="#B03E6D" />
          <circle cx="20" cy="20" r="1.5" fill="#F9A8C5" />
          <defs>
            <radialGradient id="bow-shine-l" cx="0.3" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.6" />
              <stop offset="1" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
            <radialGradient id="bow-shine-r" cx="0.7" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.6" />
              <stop offset="1" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
          </defs>
        </svg>
      );
    case 'beret':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('tilt')}>
          <ellipse cx="20" cy="28" rx="14" ry="4" fill="#000" opacity="0.1" />
          <ellipse cx="20" cy="24" rx="14" ry="4" fill="#1C1C1C" />
          <ellipse cx="20" cy="20" rx="12" ry="8" fill="#C1272D" />
          <ellipse cx="17" cy="17" rx="5" ry="2.5" fill="#E54B50" opacity="0.7" />
          <circle cx="23" cy="13" r="2" fill="#1C1C1C" />
          <circle cx="22.5" cy="12.5" r="0.6" fill="#fff" opacity="0.5" />
        </svg>
      );
    case 'flower-crown':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('spin-slow')}>
          <path d="M4 24q0-8 16-8t16 8" stroke="#7AB368" strokeWidth="2" fill="none" strokeLinecap="round" />
          <g>
            <circle cx="8" cy="18" r="3.5" fill="#F27BA7" />
            <circle cx="8" cy="18" r="1.5" fill="#FFE66D" />
          </g>
          <g>
            <circle cx="16" cy="14" r="4" fill="#F9B5D3" />
            <circle cx="16" cy="14" r="1.8" fill="#FFE66D" />
          </g>
          <g>
            <circle cx="24" cy="14" r="4" fill="#C7A8E8" />
            <circle cx="24" cy="14" r="1.8" fill="#FFE66D" />
          </g>
          <g>
            <circle cx="32" cy="18" r="3.5" fill="#F9A88B" />
            <circle cx="32" cy="18" r="1.5" fill="#FFE66D" />
          </g>
          <circle cx="20" cy="11" r="2" fill="#FFF8D6" />
        </svg>
      );
    case 'star-halo':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('glow')}>
          <ellipse cx="20" cy="18" rx="16" ry="5" stroke="#FFD54A" strokeWidth="2" fill="none" opacity="0.8" />
          <ellipse cx="20" cy="18" rx="16" ry="5" stroke="#FFF3B0" strokeWidth="1" fill="none" />
          <path d="M20 6l2.5 6 6.5.8-5 4 1.5 6.5L20 20l-5.5 3.3 1.5-6.5-5-4 6.5-.8z" fill="#FFD54A" />
          <path d="M20 6l2.5 6 6.5.8-5 4 1.5 6.5L20 20l-5.5 3.3 1.5-6.5-5-4 6.5-.8z" fill="url(#halo-shine)" />
          <circle cx="6" cy="18" r="2" fill="#FFF3B0" />
          <circle cx="34" cy="18" r="2" fill="#FFF3B0" />
          <circle cx="12" cy="24" r="1.3" fill="#FFE066" />
          <circle cx="28" cy="24" r="1.3" fill="#FFE066" />
          <defs>
            <radialGradient id="halo-shine" cx="0.4" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.7" />
              <stop offset="1" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
          </defs>
        </svg>
      );

    // ── Outfits: face ───────────────────────────────────────
    case 'glasses':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <circle cx="13" cy="20" r="7" fill="#fff" fillOpacity="0.35" stroke="#3D2915" strokeWidth="2.5" />
          <circle cx="27" cy="20" r="7" fill="#fff" fillOpacity="0.35" stroke="#3D2915" strokeWidth="2.5" />
          <path d="M20 20h0" stroke="#3D2915" strokeWidth="2.5" strokeLinecap="round" />
          <path d="M6 20H2M34 20h4" stroke="#3D2915" strokeWidth="1.8" strokeLinecap="round" />
          <ellipse cx="11" cy="17" rx="2" ry="1" fill="#fff" opacity="0.7" />
          <ellipse cx="25" cy="17" rx="2" ry="1" fill="#fff" opacity="0.7" />
        </svg>
      );
    case 'sunglasses':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <path d="M4 18h10c2 0 3 2 3 4s-1 4-2 4H8c-2 0-4-2-4-4v-4z" fill="#1C1C1C" stroke="#000" strokeWidth="1.5" />
          <path d="M36 18H26c-2 0-3 2-3 4s1 4 2 4h7c2 0 4-2 4-4v-4z" fill="#1C1C1C" stroke="#000" strokeWidth="1.5" />
          <path d="M19 21h2" stroke="#1C1C1C" strokeWidth="1.5" strokeLinecap="round" />
          <path d="M6 20l4 3M28 20l4 3" stroke="#6BC4FF" strokeWidth="1.2" strokeLinecap="round" opacity="0.8" />
        </svg>
      );
    case 'heart-eyes':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('beat')}>
          <path d="M10 14c0-3 2-5 4-3 2-2 4 0 4 3 0 4-4 7-4 7s-4-3-4-7z" fill="#E54B6E" />
          <path d="M22 14c0-3 2-5 4-3 2-2 4 0 4 3 0 4-4 7-4 7s-4-3-4-7z" fill="#E54B6E" />
          <path d="M10 14c0-3 2-5 4-3 2-2 4 0 4 3 0 4-4 7-4 7s-4-3-4-7z" fill="url(#heart-shine-l)" />
          <path d="M22 14c0-3 2-5 4-3 2-2 4 0 4 3 0 4-4 7-4 7s-4-3-4-7z" fill="url(#heart-shine-r)" />
          <path d="M14 27q6 4 12 0" stroke="#B03E50" strokeWidth="1.8" strokeLinecap="round" fill="none" />
          <defs>
            <radialGradient id="heart-shine-l" cx="0.3" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.7" />
              <stop offset="0.7" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
            <radialGradient id="heart-shine-r" cx="0.3" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.7" />
              <stop offset="0.7" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
          </defs>
        </svg>
      );

    // ── Outfits: neck ───────────────────────────────────────
    case 'scarf':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('flutter')}>
          <path d="M8 16q12-4 24 0" stroke="#D9634B" strokeWidth="4" fill="none" strokeLinecap="round" />
          <path d="M8 16q12-4 24 0" stroke="#F08A6D" strokeWidth="1.5" fill="none" strokeLinecap="round" opacity="0.6" />
          <path d="M28 16v14l-2 2v-14z" fill="#D9634B" />
          <path d="M24 16v12l-2 2v-12z" fill="#B54A35" opacity="0.85" />
          <rect x="22" y="28" width="8" height="1.5" fill="#8B3522" opacity="0.6" />
        </svg>
      );
    case 'pearl':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('shimmer')}>
          <path d="M6 18q6 10 14 10t14-10" stroke="#D4A373" strokeWidth="1" fill="none" opacity="0.5" />
          {[0, 1, 2, 3, 4, 5].map((i) => {
            const t = i / 5;
            const x = 8 + 24 * t;
            const y = 18 + Math.sin(t * Math.PI) * 8;
            return (
              <g key={i}>
                <circle cx={x} cy={y} r="3" fill="#FBF5E6" stroke="#D4A373" strokeWidth="0.6" />
                <circle cx={x - 0.8} cy={y - 0.8} r="0.9" fill="#fff" />
              </g>
            );
          })}
        </svg>
      );
    case 'friendship-charm':
      // Two interlocking hearts dangle from a gold chain — symbol of the
      // referral bond. Heart-beat animation pulls the eye to the rare item.
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('beat')}>
          <defs>
            <linearGradient id="charm-chain" x1="0" y1="0" x2="1" y2="0">
              <stop offset="0" stopColor="#E8C76B" />
              <stop offset="0.5" stopColor="#FFE08A" />
              <stop offset="1" stopColor="#E8C76B" />
            </linearGradient>
            <radialGradient id="charm-heart-pink" cx="0.35" cy="0.35">
              <stop offset="0" stopColor="#FFC2D8" />
              <stop offset="0.6" stopColor="#F27BA7" />
              <stop offset="1" stopColor="#B03E6D" />
            </radialGradient>
            <radialGradient id="charm-heart-gold" cx="0.35" cy="0.35">
              <stop offset="0" stopColor="#FFF1B5" />
              <stop offset="0.6" stopColor="#F6B94E" />
              <stop offset="1" stopColor="#A56F1A" />
            </radialGradient>
          </defs>
          {/* Gold chain — slight U-curve */}
          <path d="M5 12q15 8 30 0" stroke="url(#charm-chain)" strokeWidth="1.4" fill="none" strokeLinecap="round" />
          {/* Tiny clasp */}
          <circle cx="20" cy="17.5" r="1.1" fill="#E8C76B" />
          {/* Two interlocking hearts */}
          <g transform="translate(15.5 26) rotate(-12)">
            <path d="M0 -4c-2.5-2.8-7-1.6-7 1.8 0 3.6 4 6.5 7 8.6 3-2.1 7-5 7-8.6 0-3.4-4.5-4.6-7-1.8z" fill="url(#charm-heart-pink)" />
            <path d="M-4 -4c-1-1-2.5-0.5-2.5 0.8 0 1 1 2 2.5 3" stroke="#fff" strokeWidth="0.8" strokeLinecap="round" fill="none" opacity="0.7" />
          </g>
          <g transform="translate(24.5 28) rotate(14)">
            <path d="M0 -4c-2.5-2.8-7-1.6-7 1.8 0 3.6 4 6.5 7 8.6 3-2.1 7-5 7-8.6 0-3.4-4.5-4.6-7-1.8z" fill="url(#charm-heart-gold)" />
            <path d="M-4 -4c-1-1-2.5-0.5-2.5 0.8 0 1 1 2 2.5 3" stroke="#fff" strokeWidth="0.8" strokeLinecap="round" fill="none" opacity="0.7" />
          </g>
          {/* Sparkle */}
          <path d="M32 22l0.6 1.6 1.6 0.6-1.6 0.6-0.6 1.6-0.6-1.6-1.6-0.6 1.6-0.6z" fill="#FFE08A" opacity="0.9" />
        </svg>
      );

    // ── Backdrops ───────────────────────────────────────────
    case 'meadow':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <rect x="0" y="26" width="40" height="14" fill="#9CCB7A" />
          <path d="M0 26q10-3 20 0t20 0" stroke="#6EA355" strokeWidth="2" fill="none" />
          <circle cx="8" cy="22" r="1.5" fill="#FFE066" />
          <circle cx="22" cy="24" r="1.5" fill="#F9A8C5" />
          <circle cx="32" cy="20" r="1.5" fill="#fff" />
          <path d="M16 30v-6M18 27v-3" stroke="#6EA355" strokeWidth="0.8" />
        </svg>
      );
    case 'garden':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('pulse')}>
          <circle cx="20" cy="20" r="16" fill="#FDE9F1" />
          {[0, 72, 144, 216, 288].map((deg) => (
            <ellipse key={deg} cx="20" cy="11" rx="4" ry="6" fill="#F9A8C5" transform={`rotate(${deg} 20 20)`} />
          ))}
          <circle cx="20" cy="20" r="3" fill="#FFE066" />
        </svg>
      );
    case 'sakura':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('fall')}>
          <rect width="40" height="40" fill="#FFF2F7" />
          {[
            { cx: 10, cy: 8, r: 3 },
            { cx: 28, cy: 14, r: 4 },
            { cx: 18, cy: 22, r: 3 },
            { cx: 32, cy: 30, r: 2.5 },
            { cx: 8, cy: 32, r: 2.5 },
          ].map((p, i) => (
            <g key={i}>
              {[0, 72, 144, 216, 288].map((deg) => (
                <ellipse
                  key={deg}
                  cx={p.cx}
                  cy={p.cy - p.r}
                  rx={p.r * 0.5}
                  ry={p.r * 0.9}
                  fill={i % 2 ? '#F9A8C5' : '#FCC8DB'}
                  transform={`rotate(${deg} ${p.cx} ${p.cy})`}
                />
              ))}
              <circle cx={p.cx} cy={p.cy} r={p.r * 0.25} fill="#FFE066" />
            </g>
          ))}
        </svg>
      );
    case 'starry':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('twinkle')}>
          <rect width="40" height="40" fill="#1E2451" />
          <rect width="40" height="40" fill="url(#starry-grad)" />
          {[
            { x: 8, y: 8, s: 1.2 },
            { x: 24, y: 6, s: 0.8 },
            { x: 32, y: 14, s: 1 },
            { x: 14, y: 18, s: 0.6 },
            { x: 28, y: 24, s: 1.4 },
            { x: 6, y: 28, s: 0.9 },
            { x: 20, y: 32, s: 0.7 },
            { x: 34, y: 32, s: 1.1 },
          ].map((p, i) => (
            <path
              key={i}
              d={`M${p.x} ${p.y - p.s * 2}l${p.s * 0.5} ${p.s}l${p.s * 2} ${p.s * 0.3}l-${p.s * 1.5} ${p.s * 0.8}l${p.s * 0.6} ${p.s * 2}l-${p.s * 1.6} -${p.s}l-${p.s * 1.6} ${p.s}l${p.s * 0.6} -${p.s * 2}l-${p.s * 1.5} -${p.s * 0.8}l${p.s * 2} -${p.s * 0.3}z`}
              fill="#FFE066"
            />
          ))}
          <circle cx="30" cy="10" r="3" fill="#FFF8D6" />
          <defs>
            <linearGradient id="starry-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#3B2F7A" />
              <stop offset="1" stopColor="#1E2451" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'rainbow':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('pulse')}>
          <rect width="40" height="40" fill="#E7F3FF" />
          <path d="M4 34a16 16 0 0132 0" stroke="#E54B50" strokeWidth="3" fill="none" />
          <path d="M7 34a13 13 0 0126 0" stroke="#F5A623" strokeWidth="3" fill="none" />
          <path d="M10 34a10 10 0 0120 0" stroke="#FFE066" strokeWidth="3" fill="none" />
          <path d="M13 34a7 7 0 0114 0" stroke="#7AC74F" strokeWidth="3" fill="none" />
          <path d="M16 34a4 4 0 018 0" stroke="#4A9ECD" strokeWidth="3" fill="none" />
          <circle cx="6" cy="32" r="2.5" fill="#fff" />
          <circle cx="8" cy="30" r="2" fill="#fff" opacity="0.8" />
          <circle cx="34" cy="32" r="2.5" fill="#fff" />
          <circle cx="32" cy="30" r="2" fill="#fff" opacity="0.8" />
        </svg>
      );
    case 'beach':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('wave')}>
          <rect width="40" height="24" fill="#7EC8E3" />
          <circle cx="32" cy="8" r="4" fill="#FFE066" />
          <path d="M0 24q10-2 20 0t20 0v4H0z" fill="#4A9ECD" />
          <rect x="0" y="28" width="40" height="12" fill="#F5DEB3" />
          <circle cx="8" cy="32" r="0.8" fill="#D4A373" />
          <circle cx="18" cy="36" r="0.8" fill="#D4A373" />
          <circle cx="30" cy="34" r="0.8" fill="#D4A373" />
        </svg>
      );
    case 'friend-picnic':
      // Sunset-pink-purple sky + 2 small mascot silhouettes side-by-side
      // on a checkered picnic blanket. Floating hearts twinkle above.
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('shimmer')}>
          <defs>
            <linearGradient id="picnic-sky" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#FCD8E5" />
              <stop offset="0.55" stopColor="#F8B5C8" />
              <stop offset="1" stopColor="#C56C97" />
            </linearGradient>
            <linearGradient id="picnic-blanket" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#E54B50" />
              <stop offset="1" stopColor="#B03E6D" />
            </linearGradient>
          </defs>
          <rect width="40" height="40" fill="url(#picnic-sky)" />
          <circle cx="32" cy="9" r="3.5" fill="#FFE7B3" opacity="0.85" />
          {/* Floating hearts */}
          <path d="M9 9c-1.2-1.2-3-0.4-3 1 0 1.2 1.6 2.5 3 3.5 1.4-1 3-2.3 3-3.5 0-1.4-1.8-2.2-3-1z" fill="#fff" opacity="0.7" />
          <path d="M22 5c-0.8-0.8-2-0.3-2 0.7 0 0.8 1.1 1.7 2 2.3 0.9-0.6 2-1.5 2-2.3 0-1-1.2-1.5-2-0.7z" fill="#fff" opacity="0.5" />
          {/* Picnic blanket — checkered */}
          <rect x="2" y="28" width="36" height="12" fill="url(#picnic-blanket)" />
          <path d="M2 31h36M2 35h36M10 28v12M20 28v12M30 28v12" stroke="#fff" strokeWidth="0.6" opacity="0.45" />
          {/* Two mascot silhouettes — sprout-shape */}
          <ellipse cx="14" cy="24" rx="5" ry="6" fill="#5C9D5C" />
          <path d="M14 18c0-3 2-4 2-5.5 0-1-2-1-2 1.5z" fill="#7AB368" />
          <ellipse cx="26" cy="24" rx="5" ry="6" fill="#5C9D5C" />
          <path d="M26 18c0-3-2-4-2-5.5 0-1 2-1 2 1.5z" fill="#7AB368" />
          {/* Eye dots */}
          <circle cx="12.5" cy="24" r="0.8" fill="#1c1c1c" />
          <circle cx="15.5" cy="24" r="0.8" fill="#1c1c1c" />
          <circle cx="24.5" cy="24" r="0.8" fill="#1c1c1c" />
          <circle cx="27.5" cy="24" r="0.8" fill="#1c1c1c" />
        </svg>
      );

    // ── Achievements (colorful variants) ────────────────────
    case 'shopping-bag':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('bob')}>
          <path d="M10 12h20l2 24H8z" fill="#F27BA7" />
          <path d="M10 12h20l2 24H8z" fill="url(#bag-shine)" />
          <path d="M14 12V9a6 6 0 0112 0v3" stroke="#B03E6D" strokeWidth="2.5" strokeLinecap="round" fill="none" />
          <defs>
            <linearGradient id="bag-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.3" />
              <stop offset="0.5" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'book':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('tilt')}>
          <path d="M6 32a2 2 0 012-2h24V6H8a2 2 0 00-2 2z" fill="#F9A88B" />
          <path d="M6 32a2 2 0 012-2h24" stroke="#B03E2D" strokeWidth="2" fill="none" />
          <path d="M12 12h14M12 18h12M12 24h10" stroke="#B03E2D" strokeWidth="1.4" strokeLinecap="round" opacity="0.6" />
        </svg>
      );
    case 'cart':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('bob')}>
          <path d="M6 6h4l4 20h16l4-14H12" stroke="#9F6B3E" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <path d="M12 12h22" stroke="#D6A472" strokeWidth="2" strokeLinecap="round" opacity="0.5" />
          <circle cx="16" cy="32" r="2.5" fill="#9F6B3E" />
          <circle cx="28" cy="32" r="2.5" fill="#9F6B3E" />
        </svg>
      );
    case 'party':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('spin-slow')}>
          <path d="M6 36L14 8l16 16z" fill="#F27BA7" />
          <path d="M6 36L14 8l16 16z" fill="url(#party-shine)" />
          <circle cx="28" cy="8" r="2" fill="#FFE066" />
          <circle cx="34" cy="14" r="1.5" fill="#7AC74F" />
          <circle cx="30" cy="4" r="1.2" fill="#F5A623" />
          <circle cx="36" cy="6" r="1" fill="#4A9ECD" />
          <path d="M22 4l1 3M34 10l3 1" stroke="#F5A623" strokeWidth="1.4" strokeLinecap="round" />
          <defs>
            <linearGradient id="party-shine" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.4" />
              <stop offset="1" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'star':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('glow')}>
          <path d="M20 4l4.8 10L36 15.6l-8 7.8L29.8 34 20 29l-9.8 5L12 23.4 4 15.6 15.2 14z" fill="#FFD54A" />
          <path d="M20 4l4.8 10L36 15.6l-8 7.8L29.8 34 20 29l-9.8 5L12 23.4 4 15.6 15.2 14z" fill="url(#star-shine)" />
          <defs>
            <radialGradient id="star-shine" cx="0.4" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.7" />
              <stop offset="1" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
          </defs>
        </svg>
      );
    case 'diamond':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('shimmer')}>
          <path d="M20 4L34 16 20 36 6 16z" fill="#6BC4FF" />
          <path d="M20 4L34 16H6z" fill="#A5DCFF" />
          <path d="M14 4l-8 12M26 4l8 12M20 4v12" stroke="#fff" strokeWidth="0.8" opacity="0.5" />
          <path d="M20 4L34 16 20 36 6 16z" fill="url(#diamond-shine)" />
          <defs>
            <linearGradient id="diamond-shine" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.5" />
              <stop offset="0.3" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'money-bag':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('bob')}>
          <path d="M14 10l6-6 6 6" stroke="#C19A3E" strokeWidth="2.5" strokeLinecap="round" fill="none" />
          <path d="M10 16c0 0-2 4-2 10s4 10 12 10 12-4 12-10-2-10-2-10z" fill="#E8C54A" />
          <path d="M10 16c0 0-2 4-2 10s4 10 12 10 12-4 12-10-2-10-2-10z" fill="url(#bag-money-shine)" />
          <text x="20" y="30" textAnchor="middle" fill="#7A5E1E" fontSize="14" fontWeight="900">$</text>
          <defs>
            <radialGradient id="bag-money-shine" cx="0.4" cy="0.3">
              <stop offset="0" stopColor="#fff" stopOpacity="0.5" />
              <stop offset="0.6" stopColor="#fff" stopOpacity="0" />
            </radialGradient>
          </defs>
        </svg>
      );
    case 'crown':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('glow')}>
          <path d="M6 28l4-16 6 8 4-12 4 12 6-8 4 16z" fill="#FFD54A" />
          <path d="M6 28l4-16 6 8 4-12 4 12 6-8 4 16z" fill="url(#crown-shine)" />
          <rect x="6" y="28" width="28" height="6" rx="2" fill="#E8B83E" />
          <circle cx="10" cy="14" r="1.5" fill="#E54B6E" />
          <circle cx="20" cy="10" r="1.5" fill="#6BC4FF" />
          <circle cx="30" cy="14" r="1.5" fill="#7AC74F" />
          <defs>
            <linearGradient id="crown-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.5" />
              <stop offset="0.5" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'trophy':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('glow')}>
          <path d="M12 6h16v12c0 4.4-3.6 8-8 8s-8-3.6-8-8V6z" fill="#FFD54A" />
          <path d="M12 10H6c0 5 3 8 6 8" stroke="#E8B83E" strokeWidth="2.5" fill="none" />
          <path d="M28 10h6c0 5-3 8-6 8" stroke="#E8B83E" strokeWidth="2.5" fill="none" />
          <rect x="16" y="26" width="8" height="4" rx="1" fill="#E8B83E" />
          <rect x="13" y="30" width="14" height="4" rx="2" fill="#C1272D" />
          <path d="M14 8h12v10c0 3.3-2.7 6-6 6s-6-2.7-6-6V8z" fill="url(#trophy-shine)" />
          <defs>
            <linearGradient id="trophy-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.6" />
              <stop offset="0.6" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'sparkle':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('twinkle')}>
          <path d="M20 2l3.5 11L35 16l-11 3.5L20 31l-3.5-11.5L5 16l11.5-3z" fill="#FFD54A" />
          <path d="M32 28l1.2 3.5L37 33l-3.8 1-1.2 4-1.2-4L27 33l3.8-1z" fill="#FFE066" />
          <path d="M8 30l0.8 2.5L11 33l-2.2 0.8L8 36l-0.8-2.2L5 33l2.2-0.5z" fill="#FFE066" />
        </svg>
      );
    case 'cherry-blossom':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('spin-slow')}>
          <circle cx="20" cy="20" r="4" fill="#FFE066" />
          {[0, 72, 144, 216, 288].map((deg) => (
            <ellipse key={deg} cx="20" cy="9" rx="5" ry="7" fill="#F9A8C5" transform={`rotate(${deg} 20 20)`} />
          ))}
          {[0, 72, 144, 216, 288].map((deg) => (
            <ellipse key={`${deg}-inner`} cx="20" cy="11" rx="2.5" ry="4" fill="#FCC8DB" transform={`rotate(${deg} 20 20)`} />
          ))}
          <circle cx="20" cy="20" r="2" fill="#D65689" />
        </svg>
      );
    case 'gift':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('bob')}>
          <rect x="6" y="16" width="28" height="6" rx="1" fill="#E54B6E" />
          <rect x="8" y="22" width="24" height="12" rx="1" fill="#F27BA7" />
          <rect x="18" y="16" width="4" height="18" fill="#FFE066" />
          <path d="M20 16S16 8 12 10s0 6 8 6M20 16S24 8 28 10s0 6-8 6" stroke="#FFE066" strokeWidth="2" fill="none" strokeLinecap="round" />
          <rect x="8" y="22" width="24" height="12" rx="1" fill="url(#gift-shine)" />
          <defs>
            <linearGradient id="gift-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.3" />
              <stop offset="0.6" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'fire':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('flicker')}>
          <path d="M20 4s8 8 8 18c0 5.5-3.6 10-8 10s-8-4.5-8-10c0-6 4-10 8-10 0 0-2 4-2 8s1 6 2 6 2-2 2-4c0-4-4-8-4-8s6-2 6 4c0 2-1 4-4 4" fill="#F5A623" />
          <path d="M20 10s4 4 4 10-2 8-4 8-4-2-4-8c0-3 2-5 4-5" fill="#FFE066" />
        </svg>
      );
    case 'leaf':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <path d="M20 36C12 32 6 24 6 14c6 2 12 6 14 12 2-6 8-10 14-12 0 10-6 18-14 22z" fill="#7AC74F" />
          <path d="M20 26C16 22 12 18 10 16" stroke="#4A9D5F" strokeWidth="1.5" strokeLinecap="round" fill="none" opacity="0.5" />
        </svg>
      );
    case 'herb':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <path d="M28 6c0 0 4 8-4 16C16 30 8 30 8 30s0-8 8-16S28 6 28 6z" fill="#9CCB7A" />
          <path d="M20 20C17 24 14 28 12 30" stroke="#6EA355" strokeWidth="1.5" fill="none" opacity="0.5" />
        </svg>
      );
    case 'hibiscus':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('pulse')}>
          <circle cx="20" cy="20" r="5" fill="#FFE066" />
          {[0, 60, 120, 180, 240, 300].map((deg) => (
            <ellipse key={deg} cx="20" cy="9" rx="4.5" ry="8" fill="#E54B6E" transform={`rotate(${deg} 20 20)`} opacity="0.85" />
          ))}
          <circle cx="20" cy="20" r="2" fill="#B03E3E" />
          <circle cx="20" cy="20" r="0.8" fill="#FFE066" />
        </svg>
      );
    case 'handshake':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('pulse')}>
          <path d="M4 20l8-8 6 4 4-4 6 4 8-8" stroke="#F5A88B" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <path d="M12 24l6 6 4-4 4 4 6-6" stroke="#D9634B" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <path d="M4 20l8 10M36 20l-8 10" stroke="#F5A88B" strokeWidth="3" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'clipboard':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('tilt')}>
          <rect x="10" y="6" width="20" height="30" rx="2.5" fill="#7AC74F" />
          <rect x="10" y="6" width="20" height="30" rx="2.5" fill="url(#clip-shine)" />
          <path d="M14 6V4a2 2 0 012-2h8a2 2 0 012 2v2" stroke="#4A9D5F" strokeWidth="2" fill="#FFE066" />
          <line x1="15" y1="16" x2="25" y2="16" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" />
          <line x1="15" y1="22" x2="23" y2="22" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" />
          <line x1="15" y1="28" x2="20" y2="28" stroke="#fff" strokeWidth="1.6" strokeLinecap="round" />
          <defs>
            <linearGradient id="clip-shine" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0" stopColor="#fff" stopOpacity="0.3" />
              <stop offset="0.6" stopColor="#fff" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>
      );
    case 'sprout':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('sway')}>
          <path d="M20 36V20" stroke="#6EA355" strokeWidth="3" strokeLinecap="round" />
          <path d="M20 24C20 16 26 12 34 12c0 8-6 12-14 12z" fill="#7AC74F" />
          <path d="M20 28C20 20 14 16 6 16c0 8 6 12 14 12z" fill="#9CCB7A" />
          <circle cx="34" cy="12" r="2" fill="#FFE066" opacity="0.7" />
        </svg>
      );
    case 'compass':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('spin-slow')}>
          <circle cx="20" cy="20" r="16" fill="#6BC4FF" />
          <circle cx="20" cy="20" r="14" fill="#A5DCFF" opacity="0.5" />
          <path d="M20 6l4 12-4 2-4-2z" fill="#E54B50" />
          <path d="M20 34l-4-12 4-2 4 2z" fill="#fff" />
          <circle cx="20" cy="20" r="2" fill="#1C1C1C" />
        </svg>
      );
    case 'flower':
      return (
        <svg className={cls} style={s} viewBox="0 0 40 40" fill="none" aria-hidden data-anim={anim('spin-slow')}>
          <circle cx="20" cy="20" r="5" fill="#FFE066" />
          {[0, 90, 180, 270].map((deg) => (
            <ellipse key={deg} cx="20" cy="9" rx="5" ry="7" fill="#F9A88B" transform={`rotate(${deg} 20 20)`} />
          ))}
          {[45, 135, 225, 315].map((deg) => (
            <ellipse key={deg} cx="20" cy="10" rx="3.5" ry="5" fill="#F27BA7" transform={`rotate(${deg} 20 20)`} opacity="0.85" />
          ))}
          <circle cx="20" cy="20" r="2" fill="#D9634B" />
        </svg>
      );

    default:
      return <SiteIcon name={name} size={size} className={className} />;
  }
}
