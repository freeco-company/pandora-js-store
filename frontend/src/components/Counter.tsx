'use client';

/** Animated number counter — counts up when scrolled into view. */

import { useEffect, useRef, useState } from 'react';

interface Props {
  to: number;
  from?: number;
  duration?: number;
  suffix?: string;
  prefix?: string;
  className?: string;
  format?: (n: number) => string;
  /** Skip comma thousands separator (e.g. years). */
  raw?: boolean;
}

export default function Counter({
  to,
  from = 0,
  duration = 1400,
  suffix = '',
  prefix = '',
  className = '',
  format,
  raw = false,
}: Props) {
  const ref = useRef<HTMLSpanElement>(null);
  const [value, setValue] = useState(from);
  const triggered = useRef(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
      setValue(to);
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (!e.isIntersecting || triggered.current) return;
          triggered.current = true;
          const start = performance.now();
          const tick = (now: number) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3); // easeOutCubic
            setValue(Math.round(from + (to - from) * eased));
            if (t < 1) requestAnimationFrame(tick);
          };
          requestAnimationFrame(tick);
          io.disconnect();
        });
      },
      { threshold: 0.5 }
    );
    io.observe(el);
    return () => io.disconnect();
  }, [from, to, duration]);

  return (
    <span ref={ref} className={className}>
      {prefix}
      {format ? format(value) : raw ? String(value) : value.toLocaleString()}
      {suffix}
    </span>
  );
}
