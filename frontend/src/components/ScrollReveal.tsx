'use client';

import { useInView } from '@/hooks/useInView';
import type { ReactNode, CSSProperties } from 'react';

type Variant = 'fade-up' | 'fade-down' | 'fade-left' | 'fade-right' | 'zoom-in' | 'blur-in' | 'tilt';

interface ScrollRevealProps {
  children: ReactNode;
  delay?: number;
  className?: string;
  variant?: Variant;
  /** Distance (px) to slide from — defaults to 24 for fade-* variants */
  distance?: number;
}

export default function ScrollReveal({
  children,
  delay = 0,
  className = '',
  variant = 'fade-up',
  distance = 24,
}: ScrollRevealProps) {
  const { ref, inView } = useInView({ threshold: 0.1, triggerOnce: true });

  const style = buildStyle(variant, distance, delay, inView);

  return (
    <div ref={ref} className={className} style={style}>
      {children}
    </div>
  );
}

function buildStyle(variant: Variant, distance: number, delay: number, inView: boolean): CSSProperties {
  const base: CSSProperties = {
    transition: `opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1), filter 0.8s cubic-bezier(0.16, 1, 0.3, 1)`,
    transitionDelay: `${delay}ms`,
    willChange: 'opacity, transform, filter',
  };

  if (inView) return { ...base, opacity: 1, transform: 'none', filter: 'none' };

  switch (variant) {
    case 'fade-up':
      return { ...base, opacity: 0, transform: `translateY(${distance}px)` };
    case 'fade-down':
      return { ...base, opacity: 0, transform: `translateY(-${distance}px)` };
    case 'fade-left':
      return { ...base, opacity: 0, transform: `translateX(${distance}px)` };
    case 'fade-right':
      return { ...base, opacity: 0, transform: `translateX(-${distance}px)` };
    case 'zoom-in':
      return { ...base, opacity: 0, transform: `scale(0.92)` };
    case 'blur-in':
      return { ...base, opacity: 0, filter: 'blur(12px)', transform: `translateY(${distance / 2}px)` };
    case 'tilt':
      return {
        ...base,
        opacity: 0,
        transform: `perspective(900px) rotateX(18deg) translateY(${distance}px)`,
        transformOrigin: 'center bottom',
      };
    default:
      return { ...base, opacity: 0, transform: `translateY(${distance}px)` };
  }
}
