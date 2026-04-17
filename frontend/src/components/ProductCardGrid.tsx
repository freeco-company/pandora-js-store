'use client';

import type { CSSProperties } from 'react';
import type { Product } from '@/lib/api';
import ProductCard from './ProductCard';
import { useInView } from '@/hooks/useInView';

interface ProductCardGridProps {
  products: Product[];
  staggerKey?: string;
}

export default function ProductCardGrid({ products, staggerKey }: ProductCardGridProps) {
  const { ref, inView } = useInView({ threshold: 0.1, triggerOnce: true });

  return (
    <div
      key={staggerKey}
      ref={ref as React.Ref<HTMLDivElement>}
      className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5"
    >
      {products.map((product, index) => (
        <div
          key={product.id}
          className={`animate-on-scroll${inView ? ' in-view' : ''}`}
          style={{ '--stagger-delay': `${index * 60}ms` } as CSSProperties}
        >
          <ProductCard product={product} />
        </div>
      ))}
    </div>
  );
}
