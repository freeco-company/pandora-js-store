'use client';

/**
 * Custom cursor — beige blob that follows mouse, enlarges over interactive
 * elements, and morphs into a "view" label over product cards.
 *
 * Disabled on touch devices and when prefers-reduced-motion is set.
 */

import { useEffect, useRef, useState } from 'react';

export default function CustomCursor() {
  const outerRef = useRef<HTMLDivElement>(null);
  const innerRef = useRef<HTMLDivElement>(null);
  const [hovering, setHovering] = useState<'none' | 'link' | 'card' | 'text'>('none');
  const [label, setLabel] = useState<string>('');
  const [enabled, setEnabled] = useState(false);

  useEffect(() => {
    // Enable only for devices that have a real hover-capable fine pointer
    // (i.e. actual desktop mouse — excludes touch devices AND devtools mobile emulation)
    const fine = window.matchMedia('(pointer: fine) and (hover: hover)').matches;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const touchCapable = 'ontouchstart' in window || (navigator as Navigator & { maxTouchPoints?: number }).maxTouchPoints! > 0;
    setEnabled(fine && !reduced && !touchCapable);
  }, []);

  useEffect(() => {
    if (!enabled) return;

    let mx = -100,
      my = -100;
    let ox = -100,
      oy = -100;
    let ix = -100,
      iy = -100;
    let raf = 0;

    const onMove = (e: MouseEvent) => {
      mx = e.clientX;
      my = e.clientY;
    };

    const loop = () => {
      // Outer ring: fast catch-up (was 0.18 — too laggy)
      ox += (mx - ox) * 0.38;
      oy += (my - oy) * 0.38;
      // Inner dot: near-1:1 with pointer
      ix += (mx - ix) * 0.9;
      iy += (my - iy) * 0.9;

      if (outerRef.current) {
        outerRef.current.style.transform = `translate3d(${ox}px, ${oy}px, 0)`;
      }
      if (innerRef.current) {
        innerRef.current.style.transform = `translate3d(${ix}px, ${iy}px, 0)`;
      }
      raf = requestAnimationFrame(loop);
    };
    raf = requestAnimationFrame(loop);

    const onOver = (e: MouseEvent) => {
      const t = e.target as HTMLElement;
      if (!t) return;

      // Product card
      const card = t.closest('[data-cursor="card"]');
      if (card) {
        setHovering('card');
        setLabel(card.getAttribute('data-cursor-label') || '查看');
        return;
      }

      // Links & buttons
      const link = t.closest('a, button, [role="button"]');
      if (link && !link.hasAttribute('data-cursor-ignore')) {
        setHovering('link');
        setLabel('');
        return;
      }

      // Inputs & text
      if (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable) {
        setHovering('text');
        setLabel('');
        return;
      }

      setHovering('none');
      setLabel('');
    };

    // Press/release — cursor dilates when mouse button is down
    const onDown = () => {
      outerRef.current?.classList.add('cc-press');
      innerRef.current?.classList.add('cc-press');
    };
    const onUp = () => {
      outerRef.current?.classList.remove('cc-press');
      innerRef.current?.classList.remove('cc-press');
    };

    // Click ripple — expand an extra ring from click point
    const onClick = (e: MouseEvent) => {
      const ripple = document.createElement('span');
      ripple.className = 'cc-ripple';
      ripple.style.left = `${e.clientX}px`;
      ripple.style.top = `${e.clientY}px`;
      document.body.appendChild(ripple);
      setTimeout(() => ripple.remove(), 700);
    };

    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('mousedown', onDown, { passive: true });
    window.addEventListener('mouseup', onUp, { passive: true });
    window.addEventListener('click', onClick, { passive: true });
    document.addEventListener('mouseover', onOver, { passive: true });

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mousedown', onDown);
      window.removeEventListener('mouseup', onUp);
      window.removeEventListener('click', onClick);
      document.removeEventListener('mouseover', onOver);
    };
  }, [enabled]);

  if (!enabled) return null;

  return (
    <>
      {/* Outer ring — bigger, lags */}
      <div
        ref={outerRef}
        aria-hidden
        className={`cc-outer ${hovering}`}
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          pointerEvents: 'none',
          zIndex: 9999,
        }}
      >
        {label && <span className="cc-label">{label}</span>}
      </div>

      {/* Inner dot — faster, tight */}
      <div
        ref={innerRef}
        aria-hidden
        className={`cc-inner ${hovering}`}
        style={{
          position: 'fixed',
          top: 0,
          left: 0,
          pointerEvents: 'none',
          zIndex: 9999,
        }}
      />

      <style jsx global>{`
        /* Hide native cursor ONLY on real desktop (pointer: fine + hover: hover).
           This keeps mobile / touch tablets untouched. */
        @media (pointer: fine) and (hover: hover) and (prefers-reduced-motion: no-preference) {
          html, body { cursor: none; }
          a, button, [role="button"], input, textarea, select, [data-cursor] { cursor: none !important; }
        }

        .cc-outer {
          width: 36px;
          height: 36px;
          margin: -18px 0 0 -18px;
          border-radius: 999px;
          border: 1.5px solid rgba(159, 107, 62, 0.5);
          background: rgba(231, 217, 203, 0.15);
          backdrop-filter: blur(2px);
          transition:
            width 0.28s cubic-bezier(0.2, 0.9, 0.3, 1.1),
            height 0.28s cubic-bezier(0.2, 0.9, 0.3, 1.1),
            margin 0.28s cubic-bezier(0.2, 0.9, 0.3, 1.1),
            background 0.2s ease,
            border-color 0.2s ease,
            border-width 0.2s ease;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        .cc-outer.link {
          width: 54px;
          height: 54px;
          margin: -27px 0 0 -27px;
          background: rgba(159, 107, 62, 0.18);
          border-color: rgba(159, 107, 62, 0.7);
        }
        .cc-outer.card {
          width: 80px;
          height: 80px;
          margin: -40px 0 0 -40px;
          background: rgba(159, 107, 62, 0.9);
          border-color: rgba(159, 107, 62, 0);
        }
        .cc-outer.text {
          width: 4px;
          height: 28px;
          margin: -14px 0 0 -2px;
          border-radius: 2px;
          background: rgba(159, 107, 62, 0.8);
          border: none;
        }

        .cc-label {
          font-size: 11px;
          font-weight: 900;
          letter-spacing: 0.05em;
          color: white;
          opacity: 0;
          transition: opacity 0.15s 0.05s ease;
        }
        .cc-outer.card .cc-label { opacity: 1; }

        .cc-inner {
          width: 6px;
          height: 6px;
          margin: -3px 0 0 -3px;
          border-radius: 999px;
          background: #9F6B3E;
          transition: opacity 0.2s ease, width 0.18s ease, height 0.18s ease, margin 0.18s ease;
        }
        .cc-inner.card, .cc-inner.text { opacity: 0; }
        .cc-inner.link { background: #85572F; }

        /* Press state — both rings squash */
        .cc-outer.cc-press {
          width: 28px !important;
          height: 28px !important;
          margin: -14px 0 0 -14px !important;
          background: rgba(159, 107, 62, 0.28);
        }
        .cc-inner.cc-press {
          width: 4px;
          height: 4px;
          margin: -2px 0 0 -2px;
        }

        /* Click ripple */
        @keyframes cc-ripple-out {
          0% { transform: translate(-50%, -50%) scale(0); opacity: 0.7; }
          100% { transform: translate(-50%, -50%) scale(6); opacity: 0; }
        }
        :global(.cc-ripple) {
          position: fixed;
          width: 28px;
          height: 28px;
          border-radius: 50%;
          border: 2px solid rgba(159, 107, 62, 0.6);
          pointer-events: none;
          z-index: 9998;
          animation: cc-ripple-out 0.6s ease-out forwards;
          will-change: transform, opacity;
        }
      `}</style>
    </>
  );
}
