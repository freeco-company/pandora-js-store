'use client';

import { useEffect, useRef, useState, useCallback } from 'react';

interface UseInViewOptions {
  threshold?: number;
  rootMargin?: string;
  triggerOnce?: boolean;
}

export function useInView({
  threshold = 0.1,
  rootMargin = '0px',
  triggerOnce = true,
}: UseInViewOptions = {}) {
  const [inView, setInView] = useState(false);
  const elementRef = useRef<HTMLElement | null>(null);
  const observerRef = useRef<IntersectionObserver | null>(null);

  const ref = useCallback(
    (node: HTMLElement | null) => {
      if (observerRef.current) {
        observerRef.current.disconnect();
      }

      if (!node) {
        elementRef.current = null;
        return;
      }

      elementRef.current = node;

      // Above-the-fold fast-path: if the element is already visible when
      // registered, trigger immediately so users don't see a flash of empty
      // content on page load.
      const rect = node.getBoundingClientRect();
      const vh = window.innerHeight || document.documentElement.clientHeight;
      if (rect.top < vh && rect.bottom > 0) {
        setInView(true);
        node.classList.add('in-view');
        if (triggerOnce) return;
      }

      observerRef.current = new IntersectionObserver(
        ([entry]) => {
          if (entry.isIntersecting) {
            setInView(true);
            node.classList.add('in-view');
            if (triggerOnce && observerRef.current) {
              observerRef.current.disconnect();
            }
          } else if (!triggerOnce) {
            setInView(false);
            node.classList.remove('in-view');
          }
        },
        { threshold, rootMargin }
      );

      observerRef.current.observe(node);
    },
    [threshold, rootMargin, triggerOnce]
  );

  useEffect(() => {
    return () => {
      observerRef.current?.disconnect();
    };
  }, []);

  return { ref, inView };
}
