'use client';

/**
 * Lenis smooth scroll — wraps the entire app.
 * Creates the buttery, decelerated scroll feel that premium sites use.
 *
 * All 4 reference sites (artbank, fenc, touchstone, ventiventi) use
 * Lenis. Without it, scroll-driven animations feel "rough" because
 * native scroll is frame-jerky on content-heavy pages.
 */

import { useEffect, useRef } from 'react';
import Lenis from 'lenis';

export default function LenisProvider({ children }: { children: React.ReactNode }) {
  const lenisRef = useRef<Lenis | null>(null);

  useEffect(() => {
    const lenis = new Lenis({
      lerp: 0.1,         // smoothness (lower = smoother, 0.1 matches reference sites)
      duration: 1.2,      // base scroll duration
      smoothWheel: true,
      touchMultiplier: 1.5,
    });
    lenisRef.current = lenis;

    function raf(time: number) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }
    requestAnimationFrame(raf);

    return () => {
      lenis.destroy();
      lenisRef.current = null;
    };
  }, []);

  return <>{children}</>;
}
