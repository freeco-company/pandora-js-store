'use client';

/**
 * Letter-by-letter reveal. Real text lives in a visually-hidden <span>
 * for screen readers (no aria-label on <p>, which violates ARIA rules).
 * The animated per-character spans are aria-hidden.
 */

import { useEffect, useRef, useState } from 'react';

interface Props {
  text: string;
  className?: string;
  delay?: number;
  stagger?: number;
  as?: 'h1' | 'h2' | 'h3' | 'p' | 'span';
}

export default function TextReveal({
  text,
  className = '',
  delay = 0,
  stagger = 28,
  as: Tag = 'h1',
}: Props) {
  const ref = useRef<HTMLElement>(null);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
      setVisible(true);
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            setVisible(true);
            io.disconnect();
          }
        });
      },
      { threshold: 0.1 }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);

  const chars = Array.from(text);

  return (
    <Tag
      ref={ref as React.Ref<HTMLHeadingElement & HTMLParagraphElement & HTMLSpanElement>}
      className={className}
    >
      {/* Real text — invisible to sight, read by screen readers once */}
      <span className="sr-only">{text}</span>
      {/* Animated letters — hidden from AT */}
      <span aria-hidden="true">
        {chars.map((ch, i) => (
          <span
            key={i}
            className="inline-block overflow-hidden align-bottom"
          >
            <span
              className="inline-block"
              style={{
                transform: visible ? 'translateY(0)' : 'translateY(100%)',
                opacity: visible ? 1 : 0,
                transition: `transform 0.7s cubic-bezier(0.2, 0.9, 0.25, 1), opacity 0.5s ease`,
                transitionDelay: `${delay + i * stagger}ms`,
              }}
            >
              {ch === ' ' ? '\u00A0' : ch}
            </span>
          </span>
        ))}
      </span>
    </Tag>
  );
}
