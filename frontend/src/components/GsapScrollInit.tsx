'use client';

/**
 * Global GSAP ScrollTrigger initializer.
 * Drop this component anywhere — it finds all elements with gs-* classes
 * and attaches scroll-driven animations. Works on any page.
 *
 * Classes:
 *   gs-reveal   — fadeUp 120px + blur
 *   gs-scale    — scale(0.6) + blur → 1.0
 *   gs-left     — slideIn from x:-120
 *   gs-right    — slideIn from x:120
 *   gs-lines    — children .gs-line revealed line-by-line
 *   gs-stagger  — children .gs-si staggered
 *   gs-counter  — counts from 0 to data-target
 *   data-speed  — parallax at different rates
 */

import { useEffect } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default function GsapScrollInit() {
  useEffect(() => {
    // Small delay to ensure DOM is painted
    const timer = setTimeout(() => {
      const ctx = gsap.context(() => {
        // Parallax
        gsap.utils.toArray<HTMLElement>('[data-speed]').forEach((el) => {
          const speed = parseFloat(el.dataset.speed || '1');
          gsap.to(el, {
            y: () => (1 - speed) * 400,
            ease: 'none',
            scrollTrigger: { trigger: el.closest('section') || el, start: 'top bottom', end: 'bottom top', scrub: 1.5 },
          });
        });

        // Fade reveals
        gsap.utils.toArray<HTMLElement>('.gs-reveal').forEach((el) => {
          gsap.fromTo(el,
            { y: 100, opacity: 0, filter: 'blur(5px)' },
            { y: 0, opacity: 1, filter: 'blur(0px)', ease: 'power3.out',
              scrollTrigger: { trigger: el, start: 'top 88%', end: 'top 40%', scrub: 1 } },
          );
        });

        // Scale
        gsap.utils.toArray<HTMLElement>('.gs-scale').forEach((el) => {
          gsap.fromTo(el,
            { scale: 0.7, opacity: 0, filter: 'blur(8px)' },
            { scale: 1, opacity: 1, filter: 'blur(0px)', ease: 'power2.out',
              scrollTrigger: { trigger: el, start: 'top 85%', end: 'top 35%', scrub: 1.2 } },
          );
        });

        // Slides
        gsap.utils.toArray<HTMLElement>('.gs-left').forEach((el) => {
          gsap.fromTo(el, { x: -100, opacity: 0 },
            { x: 0, opacity: 1, ease: 'power3.out',
              scrollTrigger: { trigger: el, start: 'top 85%', end: 'top 40%', scrub: 1 } });
        });
        gsap.utils.toArray<HTMLElement>('.gs-right').forEach((el) => {
          gsap.fromTo(el, { x: 100, opacity: 0 },
            { x: 0, opacity: 1, ease: 'power3.out',
              scrollTrigger: { trigger: el, start: 'top 85%', end: 'top 40%', scrub: 1 } });
        });

        // Line reveals
        gsap.utils.toArray<HTMLElement>('.gs-lines').forEach((el) => {
          const lines = el.querySelectorAll('.gs-line');
          gsap.fromTo(lines,
            { y: 80, opacity: 0, rotateX: 30 },
            { y: 0, opacity: 1, rotateX: 0, stagger: 0.08, ease: 'power3.out',
              scrollTrigger: { trigger: el, start: 'top 82%', end: 'top 35%', scrub: 1 } },
          );
        });

        // Stagger
        gsap.utils.toArray<HTMLElement>('.gs-stagger').forEach((container) => {
          const children = container.querySelectorAll('.gs-si');
          gsap.fromTo(children,
            { y: 80, opacity: 0, filter: 'blur(3px)' },
            { y: 0, opacity: 1, filter: 'blur(0px)', stagger: 0.1, ease: 'power3.out',
              scrollTrigger: { trigger: container, start: 'top 78%', end: 'top 25%', scrub: 1.2 } },
          );
        });

        // Counters
        gsap.utils.toArray<HTMLElement>('.gs-counter').forEach((el) => {
          const target = parseInt(el.dataset.target || '0', 10);
          const obj = { val: 0 };
          gsap.to(obj, {
            val: target, duration: 1.5, ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 80%', toggleActions: 'play none none none' },
            onUpdate: () => { el.textContent = Math.round(obj.val).toLocaleString(); },
          });
        });
      });

      return () => ctx.revert();
    }, 100);

    return () => clearTimeout(timer);
  }, []);

  return null; // Invisible — just attaches animations
}
