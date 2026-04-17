/**
 * Centralised inline SVG icon library.
 * Replaces all emoji across the site for consistent rendering,
 * brand colour control, and animation support.
 *
 * Usage: <SiteIcon name="sprout" size={20} className="text-[#4A9D5F]" />
 *
 * All icons default to `currentColor` so they inherit the parent's text colour.
 */

interface Props {
  name: string;
  size?: number;
  className?: string;
  /** Override colour (defaults to currentColor) */
  color?: string;
}

export default function SiteIcon({ name, size = 20, className = '', color }: Props) {
  const s = { width: size, height: size, color } as React.CSSProperties;
  const cls = `inline-block shrink-0 ${className}`;

  switch (name) {
    // ── Nature / Plant ──────────────────────────────────────
    case 'sparkle':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 1l1.8 5.5H17l-4.2 3.1 1.6 5.4L10 12l-4.4 3 1.6-5.4L3 6.5h5.2z" fill="currentColor" />
        </svg>
      );
    case 'leaf':
    case 'herb':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 18C6 16 3 12 3 7c3 1 6 3 7 6C11 10 14 8 17 7c0 5-3 9-7 11z" fill="currentColor" />
          <path d="M10 13C8 11 6 9 5 8" stroke="currentColor" strokeWidth="0.8" strokeLinecap="round" fill="none" opacity="0.4" />
        </svg>
      );
    case 'sprout':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 18V10" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
          <path d="M10 12C10 9 13 7 16 7c0 3-2 5-6 5z" fill="currentColor" />
          <path d="M10 14C10 11 7 9 4 9c0 3 2 5 6 5z" fill="currentColor" opacity="0.7" />
        </svg>
      );
    case 'leaf-falling':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M14 3C14 3 16 7 14 11C12 15 7 17 7 17C7 17 5 13 7 9C9 5 14 3 14 3Z" fill="currentColor" />
          <path d="M10 10C8.5 12 7.5 14 7 17" stroke="currentColor" strokeWidth="0.8" fill="none" opacity="0.4" strokeLinecap="round" />
        </svg>
      );
    case 'cherry-blossom':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="2" fill="currentColor" opacity="0.6" />
          {[0, 72, 144, 216, 288].map((deg) => (
            <ellipse key={deg} cx="10" cy="5" rx="2.5" ry="3.5" fill="currentColor" transform={`rotate(${deg} 10 10)`} />
          ))}
        </svg>
      );
    case 'hibiscus':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="2.5" fill="currentColor" opacity="0.8" />
          {[0, 60, 120, 180, 240, 300].map((deg) => (
            <ellipse key={deg} cx="10" cy="4.5" rx="2.2" ry="4" fill="currentColor" transform={`rotate(${deg} 10 10)`} opacity="0.7" />
          ))}
        </svg>
      );
    case 'flower':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="3" fill="currentColor" opacity="0.5" />
          {[0, 90, 180, 270].map((deg) => (
            <ellipse key={deg} cx="10" cy="4.5" rx="2.5" ry="3.5" fill="currentColor" transform={`rotate(${deg} 10 10)`} />
          ))}
        </svg>
      );

    // ── Status / Achievement ────────────────────────────────
    case 'fire':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2C10 2 14 6 14 11c0 2.8-1.8 5-4 5s-4-2.2-4-5C6 8 8 6 10 6c0 0-1 2-1 4s.5 3 1 3 1-1 1-2c0-2-2-4-2-4s3-1 3 2c0 1-.5 2-2 2" fill="currentColor" />
        </svg>
      );
    case 'trophy':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M6 3h8v6c0 2.2-1.8 4-4 4s-4-1.8-4-4V3z" fill="currentColor" />
          <path d="M6 5H3c0 2.5 1.5 4 3 4" stroke="currentColor" strokeWidth="1.2" fill="none" opacity="0.5" />
          <path d="M14 5h3c0 2.5-1.5 4-3 4" stroke="currentColor" strokeWidth="1.2" fill="none" opacity="0.5" />
          <rect x="8" y="13" width="4" height="2" rx="0.5" fill="currentColor" opacity="0.7" />
          <rect x="6.5" y="15" width="7" height="2" rx="1" fill="currentColor" opacity="0.7" />
        </svg>
      );
    case 'crown':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 14l2-8 3 4 2-6 2 6 3-4 2 8z" fill="currentColor" />
          <rect x="3" y="14" width="14" height="3" rx="1" fill="currentColor" opacity="0.8" />
        </svg>
      );
    case 'diamond':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2L17 8L10 18L3 8Z" fill="currentColor" />
          <path d="M3 8h14" stroke="currentColor" strokeWidth="0.5" opacity="0.3" />
          <path d="M7 2l-4 6M13 2l4 6M10 2v6" stroke="currentColor" strokeWidth="0.5" opacity="0.3" />
        </svg>
      );
    case 'star':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2l2.4 5 5.6.8-4 3.9.9 5.3L10 14.5 5.1 17l.9-5.3-4-3.9L7.6 7z" fill="currentColor" />
        </svg>
      );
    case 'target':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="8" stroke="currentColor" strokeWidth="1.5" />
          <circle cx="10" cy="10" r="5" stroke="currentColor" strokeWidth="1.2" />
          <circle cx="10" cy="10" r="2" fill="currentColor" />
        </svg>
      );
    case 'rainbow':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M2 16a8 8 0 0116 0" stroke="currentColor" strokeWidth="1.5" fill="none" opacity="0.4" />
          <path d="M4 16a6 6 0 0112 0" stroke="currentColor" strokeWidth="1.5" fill="none" opacity="0.6" />
          <path d="M6 16a4 4 0 018 0" stroke="currentColor" strokeWidth="1.5" fill="none" opacity="0.8" />
          <path d="M8 16a2 2 0 014 0" stroke="currentColor" strokeWidth="1.5" fill="none" />
        </svg>
      );

    // ── Commerce / Action ───────────────────────────────────
    case 'shopping-bag':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M5 6h10l1 12H4L5 6z" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1.3" />
          <path d="M7 6V5a3 3 0 016 0v1" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'cart':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 3h2l2 10h8l2-7H6" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <circle cx="8" cy="16" r="1.2" fill="currentColor" />
          <circle cx="14" cy="16" r="1.2" fill="currentColor" />
        </svg>
      );
    case 'money-bag':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M7 5l3-3 3 3" stroke="currentColor" strokeWidth="1.2" fill="none" strokeLinecap="round" />
          <path d="M5 8c0 0-1 2-1 5s2 5 6 5 6-2 6-5-1-5-1-5z" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1.2" />
          <text x="10" y="14.5" textAnchor="middle" fill="currentColor" fontSize="7" fontWeight="bold">$</text>
        </svg>
      );
    case 'gift':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="3" y="8" width="14" height="3" rx="1" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="1" />
          <rect x="4" y="11" width="12" height="6" rx="1" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1" />
          <line x1="10" y1="8" x2="10" y2="17" stroke="currentColor" strokeWidth="1" />
          <path d="M10 8C10 8 8 4 6 5s0 3 4 3" stroke="currentColor" strokeWidth="1" fill="none" />
          <path d="M10 8C10 8 12 4 14 5s0 3-4 3" stroke="currentColor" strokeWidth="1" fill="none" />
        </svg>
      );
    case 'ribbon':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2a4 4 0 100 8 4 4 0 000-8z" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="1" />
          <path d="M7 9l-2 9 5-3 5 3-2-9" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1" />
        </svg>
      );

    case 'handshake':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M2 10l4-4 3 2 2-2 3 2 4-4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <path d="M6 12l3 3 2-2 2 2 3-3" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" fill="none" />
          <path d="M2 10l4 5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" fill="none" />
          <path d="M18 10l-4 5" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'check-circle':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="8" stroke="currentColor" strokeWidth="1.4" fill="currentColor" opacity="0.1" />
          <path d="M6 10l3 3 5-6" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" fill="none" />
        </svg>
      );

    // ── Misc / Utility ──────────────────────────────────────
    case 'water-drop':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2C10 2 4 9 4 13a6 6 0 1012 0C16 9 10 2 10 2z" fill="currentColor" />
        </svg>
      );
    case 'milk':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M7 3h6v3l1 2v8a2 2 0 01-2 2H8a2 2 0 01-2-2V8l1-2V3z" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1.2" />
          <path d="M6 10h8" stroke="currentColor" strokeWidth="0.8" opacity="0.5" />
          <rect x="7" y="1.5" width="6" height="2" rx="0.5" fill="currentColor" opacity="0.4" />
        </svg>
      );
    case 'muscle':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M5 13c0-3 1-5 3-6s3 0 3 2c0-2 1-3 3-2s3 3 3 6" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" />
          <path d="M5 13c-1 0-2-1-2-2s1-3 2-3" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" />
          <path d="M17 13c1 0 2-1 2-2s-1-3-2-3" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" />
        </svg>
      );
    case 'book':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 16.5A1.5 1.5 0 014.5 15H17V2H4.5A1.5 1.5 0 003 3.5v13z" fill="currentColor" opacity="0.15" stroke="currentColor" strokeWidth="1.2" />
          <path d="M3 16.5A1.5 1.5 0 014.5 15H17" stroke="currentColor" strokeWidth="1.2" fill="none" />
        </svg>
      );
    case 'mailbox':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="3" y="7" width="14" height="10" rx="2" stroke="currentColor" strokeWidth="1.3" fill="currentColor" opacity="0.1" />
          <path d="M3 10l7 4 7-4" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" fill="none" />
          <path d="M8 7V4a2 2 0 014 0v3" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'party':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 18L7 4l8 8z" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1" />
          <circle cx="14" cy="4" r="1" fill="currentColor" />
          <circle cx="17" cy="7" r="0.8" fill="currentColor" opacity="0.6" />
          <circle cx="16" cy="3" r="0.6" fill="currentColor" opacity="0.4" />
          <path d="M12 2l.5 2M17 5l2 .5" stroke="currentColor" strokeWidth="0.8" strokeLinecap="round" />
        </svg>
      );

    // ── Trust badge shapes ──────────────────────────────────
    case 'credit-card':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="2" y="4" width="16" height="12" rx="2" stroke="currentColor" strokeWidth="1.3" fill="currentColor" opacity="0.1" />
          <line x1="2" y1="8" x2="18" y2="8" stroke="currentColor" strokeWidth="1.3" />
          <rect x="4" y="11" width="4" height="2" rx="0.5" fill="currentColor" opacity="0.3" />
        </svg>
      );
    case 'truck':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="1" y="5" width="11" height="9" rx="1" stroke="currentColor" strokeWidth="1.2" fill="currentColor" opacity="0.1" />
          <path d="M12 8h4l2 3v3h-6V8z" stroke="currentColor" strokeWidth="1.2" fill="none" />
          <circle cx="5" cy="15" r="1.5" fill="currentColor" />
          <circle cx="15" cy="15" r="1.5" fill="currentColor" />
        </svg>
      );
    case 'shield':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2L3 5v5c0 4.4 3 7.5 7 9 4-1.5 7-4.6 7-9V5l-7-3z" fill="currentColor" opacity="0.15" stroke="currentColor" strokeWidth="1.2" />
          <path d="M7 10l2 2 4-4" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" fill="none" />
        </svg>
      );
    case 'headset':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M4 10V9a6 6 0 0112 0v1" stroke="currentColor" strokeWidth="1.3" fill="none" />
          <rect x="2" y="10" width="3" height="5" rx="1" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="1" />
          <rect x="15" y="10" width="3" height="5" rx="1" fill="currentColor" opacity="0.3" stroke="currentColor" strokeWidth="1" />
          <path d="M16 15c0 2-2 3-4 3h-2" stroke="currentColor" strokeWidth="1" fill="none" />
        </svg>
      );

    // ── Communication / UI ────────────────────────────────────
    case 'clipboard':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="5" y="3" width="10" height="15" rx="1.5" stroke="currentColor" strokeWidth="1.3" fill="currentColor" opacity="0.1" />
          <path d="M7 3V2a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" strokeWidth="1.2" fill="none" />
          <line x1="7.5" y1="8" x2="12.5" y2="8" stroke="currentColor" strokeWidth="1" strokeLinecap="round" />
          <line x1="7.5" y1="11" x2="11" y2="11" stroke="currentColor" strokeWidth="1" strokeLinecap="round" />
          <line x1="7.5" y1="14" x2="10" y2="14" stroke="currentColor" strokeWidth="1" strokeLinecap="round" />
        </svg>
      );
    case 'chat':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 4a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H8l-4 3v-3a2 2 0 01-1-1.7V4z" fill="currentColor" opacity="0.15" stroke="currentColor" strokeWidth="1.2" />
          <circle cx="7" cy="8" r="1" fill="currentColor" />
          <circle cx="10" cy="8" r="1" fill="currentColor" />
          <circle cx="13" cy="8" r="1" fill="currentColor" />
        </svg>
      );
    case 'phone':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="5.5" y="1" width="9" height="18" rx="2" stroke="currentColor" strokeWidth="1.3" fill="currentColor" opacity="0.1" />
          <line x1="8" y1="16" x2="12" y2="16" stroke="currentColor" strokeWidth="1" strokeLinecap="round" />
          <line x1="5.5" y1="4" x2="14.5" y2="4" stroke="currentColor" strokeWidth="0.8" opacity="0.3" />
        </svg>
      );
    case 'stethoscope':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M5 3v5a5 5 0 0010 0V3" stroke="currentColor" strokeWidth="1.3" fill="none" strokeLinecap="round" />
          <circle cx="5" cy="3" r="1.2" fill="currentColor" opacity="0.5" />
          <circle cx="15" cy="3" r="1.2" fill="currentColor" opacity="0.5" />
          <circle cx="15" cy="14" r="2" stroke="currentColor" strokeWidth="1.2" fill="currentColor" opacity="0.2" />
          <path d="M15 10v2" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" />
        </svg>
      );

    // ── Join page feature icons ─────────────────────────────
    case 'infinity':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M6 10a3 3 0 110-1 3 3 0 010 1zM14 10a3 3 0 110-1 3 3 0 010 1z" stroke="currentColor" strokeWidth="1.5" />
          <path d="M9 8.5c1-1.5 2.5-2 3.5-1.5M9 11.5c1 1.5 2.5 2 3.5 1.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'no-pressure':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="7.5" stroke="currentColor" strokeWidth="1.3" />
          <line x1="4" y1="4" x2="16" y2="16" stroke="currentColor" strokeWidth="1.3" />
        </svg>
      );

    case 'package':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M3 6l7-4 7 4v8l-7 4-7-4V6z" stroke="currentColor" strokeWidth="1.2" fill="currentColor" opacity="0.1" />
          <path d="M3 6l7 4 7-4M10 10v8" stroke="currentColor" strokeWidth="1.2" />
        </svg>
      );
    case 'lock':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <rect x="4" y="9" width="12" height="9" rx="2" stroke="currentColor" strokeWidth="1.3" fill="currentColor" opacity="0.1" />
          <path d="M7 9V6a3 3 0 016 0v3" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" fill="none" />
          <circle cx="10" cy="13.5" r="1.2" fill="currentColor" />
        </svg>
      );
    case 'no-entry':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="7.5" stroke="currentColor" strokeWidth="1.3" />
          <line x1="4" y1="4" x2="16" y2="16" stroke="currentColor" strokeWidth="1.3" />
        </svg>
      );

    case 'link':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M8.5 11.5a3.5 3.5 0 005 0l2-2a3.5 3.5 0 00-5-5l-1 1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" fill="none" />
          <path d="M11.5 8.5a3.5 3.5 0 00-5 0l-2 2a3.5 3.5 0 005 5l1-1" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" fill="none" />
        </svg>
      );
    case 'search':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" strokeWidth="1.4" />
          <path d="M12.5 12.5L17 17" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
        </svg>
      );
    case 'bell':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2a5 5 0 00-5 5v3l-1.5 2.5a.5.5 0 00.43.75h12.14a.5.5 0 00.43-.75L15 10V7a5 5 0 00-5-5z" fill="currentColor" opacity="0.15" stroke="currentColor" strokeWidth="1.2" />
          <path d="M8 15.5a2 2 0 004 0" stroke="currentColor" strokeWidth="1.2" strokeLinecap="round" fill="none" />
        </svg>
      );

    case 'compass':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <circle cx="10" cy="10" r="8" stroke="currentColor" strokeWidth="1.2" />
          <polygon points="10,4 12,9 10,10 8,9" fill="currentColor" />
          <polygon points="10,16 8,11 10,10 12,11" fill="currentColor" opacity="0.4" />
        </svg>
      );
    case 'airplane':
      return (
        <svg className={cls} style={s} viewBox="0 0 24 24" fill="none" aria-hidden>
          <path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z" fill="currentColor" />
        </svg>
      );
    case 'tent':
      return (
        <svg className={cls} style={s} viewBox="0 0 20 20" fill="none" aria-hidden>
          <path d="M10 2L2 16h16L10 2z" fill="currentColor" opacity="0.15" stroke="currentColor" strokeWidth="1.2" strokeLinejoin="round" />
          <path d="M10 2v14" stroke="currentColor" strokeWidth="0.8" opacity="0.4" />
          <path d="M7 16c0-3 1.5-5 3-7 1.5 2 3 4 3 7" stroke="currentColor" strokeWidth="1" fill="none" />
        </svg>
      );

    default:
      return null;
  }
}
