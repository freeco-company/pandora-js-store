'use client';

import type { CSSProperties, ReactNode } from 'react';
import { useInView } from '@/hooks/useInView';
import React from 'react';

export default function ArticleGridStagger({
  children,
  className = '',
}: {
  children: ReactNode;
  className?: string;
}) {
  const { ref, inView } = useInView({ threshold: 0.05, triggerOnce: true });

  return (
    <div ref={ref as React.Ref<HTMLDivElement>} className={className}>
      {React.Children.map(children, (child, index) => (
        <div
          className={`animate-on-scroll${inView ? ' in-view' : ''}`}
          style={{ '--stagger-delay': `${index * 60}ms` } as CSSProperties}
        >
          {child}
        </div>
      ))}
    </div>
  );
}
