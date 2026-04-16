'use client';

/**
 * Scroll-linked parallax. Wraps children and shifts them on scroll by
 * `strength`. Positive = scrolls slower (up), negative = faster.
 */

import { useEffect, useRef, type ReactNode } from 'react';

interface Props {
  children: ReactNode;
  strength?: number; // -0.5 .. 0.5 (0.2 default)
  className?: string;
}

export default function Parallax({ children, strength = 0.2, className = '' }: Props) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    let ticking = false;
    const update = () => {
      const rect = el.getBoundingClientRect();
      const center = rect.top + rect.height / 2;
      const viewportCenter = window.innerHeight / 2;
      const delta = center - viewportCenter;
      el.style.transform = `translate3d(0, ${-delta * strength}px, 0)`;
      ticking = false;
    };
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(update);
    };
    update();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    return () => {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    };
  }, [strength]);

  return (
    <div ref={ref} className={className} style={{ willChange: 'transform' }}>
      {children}
    </div>
  );
}
