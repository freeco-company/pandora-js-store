'use client';

/**
 * Full-screen scroll-driven story engine.
 *
 * Each "act" pins to the viewport while the user scrolls through it.
 * Progress (0→1) per act drives: background color, SVG morphing,
 * text opacity/position, and any custom render function.
 *
 * Usage:
 *   <ScrollStory acts={[
 *     { bg: ['#1a1410', '#1a3020'], render: (p) => <MyScene progress={p} /> },
 *     { bg: ['#1a3020', '#fdf7ef'], render: (p) => <AnotherScene progress={p} /> },
 *   ]} />
 *
 * Each act takes ~100vh of scroll to complete. The pinned viewport
 * shows the interpolated scene. Total scroll height = acts.length * 100vh.
 */

import { useEffect, useRef, useState, type ReactNode } from 'react';

export interface Act {
  /** Background color gradient: [startColor, endColor] interpolated over the act's progress */
  bg: [string, string];
  /** Render the scene content. `progress` goes from 0→1 as this act scrolls through. */
  render: (progress: number) => ReactNode;
}

interface Props {
  acts: Act[];
  className?: string;
}

function lerpColor(a: string, b: string, t: number): string {
  const parse = (hex: string) => {
    const h = hex.replace('#', '');
    return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
  };
  const [r1, g1, b1] = parse(a);
  const [r2, g2, b2] = parse(b);
  const r = Math.round(r1 + (r2 - r1) * t);
  const g = Math.round(g1 + (g2 - g1) * t);
  const bl = Math.round(b1 + (b2 - b1) * t);
  return `rgb(${r},${g},${bl})`;
}

export default function ScrollStory({ acts, className = '' }: Props) {
  const containerRef = useRef<HTMLDivElement>(null);
  const [globalProgress, setGlobalProgress] = useState(0);

  useEffect(() => {
    const el = containerRef.current;
    if (!el) return;

    const onScroll = () => {
      const rect = el.getBoundingClientRect();
      const scrollableHeight = el.scrollHeight - window.innerHeight;
      const scrolled = -rect.top;
      const p = Math.max(0, Math.min(1, scrolled / scrollableHeight));
      setGlobalProgress(p);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    return () => window.removeEventListener('scroll', onScroll);
  }, [acts.length]);

  // Current act index + local progress within that act
  const totalActs = acts.length;
  const actFloat = globalProgress * totalActs;
  const actIndex = Math.min(Math.floor(actFloat), totalActs - 1);
  const actProgress = actFloat - actIndex;
  const currentAct = acts[actIndex];

  // Background: interpolate current act's bg range
  const bgColor = currentAct ? lerpColor(currentAct.bg[0], currentAct.bg[1], actProgress) : '#ffffff';

  return (
    <div
      ref={containerRef}
      className={`relative ${className}`}
      style={{ height: `${totalActs * 100}vh` }}
    >
      {/* Pinned viewport */}
      <div
        className="sticky top-0 left-0 w-full h-screen overflow-hidden flex items-center justify-center transition-colors duration-100"
        style={{ backgroundColor: bgColor }}
      >
        {currentAct?.render(actProgress)}

        {/* Scroll progress dots */}
        <div className="absolute right-4 top-1/2 -translate-y-1/2 flex flex-col gap-2 z-20">
          {acts.map((_, i) => (
            <div
              key={i}
              className="w-2 h-2 rounded-full transition-all duration-300"
              style={{
                backgroundColor: i === actIndex ? '#ffffff' : 'rgba(255,255,255,0.3)',
                transform: i === actIndex ? 'scale(1.5)' : 'scale(1)',
              }}
            />
          ))}
        </div>

        {/* Scroll hint (only on first act, fades out) */}
        {actIndex === 0 && actProgress < 0.3 && (
          <div
            className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-white/60"
            style={{ opacity: 1 - actProgress * 3 }}
          >
            <span className="text-[11px] font-bold tracking-wider">SCROLL</span>
            <svg className="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
          </div>
        )}
      </div>
    </div>
  );
}
