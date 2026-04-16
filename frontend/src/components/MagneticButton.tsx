'use client';

/**
 * Magnetic button / anchor — pulls slightly toward the cursor when hovered.
 * Usage:
 *   <MagneticButton href="/products" className="...">立即選購</MagneticButton>
 */

import Link from 'next/link';
import { useRef, type CSSProperties, type ReactNode } from 'react';

interface Props {
  href?: string;
  className?: string;
  children: ReactNode;
  onClick?: () => void;
  style?: CSSProperties;
  strength?: number; // 0..1 (0.3 default, higher = stronger pull)
  'aria-label'?: string;
}

export default function MagneticButton({
  href,
  className = '',
  children,
  onClick,
  style,
  strength = 0.35,
  ...rest
}: Props) {
  const ref = useRef<HTMLElement>(null);

  const handleMove = (e: React.MouseEvent) => {
    const el = ref.current;
    if (!el) return;
    if (window.matchMedia('(pointer: coarse)').matches) return;
    const rect = el.getBoundingClientRect();
    const dx = e.clientX - (rect.left + rect.width / 2);
    const dy = e.clientY - (rect.top + rect.height / 2);
    el.style.transform = `translate3d(${dx * strength}px, ${dy * strength}px, 0)`;
    const inner = el.querySelector('[data-magnetic-inner]') as HTMLElement | null;
    if (inner) inner.style.transform = `translate3d(${dx * strength * 0.5}px, ${dy * strength * 0.5}px, 0)`;
  };

  const handleLeave = () => {
    const el = ref.current;
    if (!el) return;
    el.style.transform = '';
    const inner = el.querySelector('[data-magnetic-inner]') as HTMLElement | null;
    if (inner) inner.style.transform = '';
  };

  const sharedStyle: CSSProperties = {
    transition: 'transform 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1)',
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    ...style,
  };

  const content = <span data-magnetic-inner style={{ transition: 'transform 0.4s cubic-bezier(0.2,0.9,0.3,1.1)', display: 'inline-flex', alignItems: 'center' }}>{children}</span>;

  if (href) {
    return (
      <Link
        href={href}
        ref={ref as React.Ref<HTMLAnchorElement>}
        className={className}
        style={sharedStyle}
        onMouseMove={handleMove}
        onMouseLeave={handleLeave}
        {...rest}
      >
        {content}
      </Link>
    );
  }

  return (
    <button
      ref={ref as React.Ref<HTMLButtonElement>}
      className={className}
      style={sharedStyle}
      onClick={onClick}
      onMouseMove={handleMove}
      onMouseLeave={handleLeave}
      {...rest}
    >
      {content}
    </button>
  );
}
