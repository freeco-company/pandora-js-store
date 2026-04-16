'use client';

import { useMemo, useState } from 'react';
import { useSearchParams, useRouter, usePathname } from 'next/navigation';
import type { Product } from '@/lib/api';
import ProductCardGrid from './ProductCardGrid';

const SORT_OPTIONS = [
  { value: '', label: '預設排序' },
  { value: 'price_asc', label: '價格低到高' },
  { value: 'price_desc', label: '價格高到低' },
  { value: 'newest', label: '最新上架' },
] as const;

export default function ProductSort({ products, staggerKey }: { products: Product[]; staggerKey?: string }) {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const currentSort = searchParams.get('sort') || '';

  const sorted = useMemo(() => {
    const arr = [...products];
    switch (currentSort) {
      case 'price_asc':
        return arr.sort((a, b) => (a.combo_price ?? a.price) - (b.combo_price ?? b.price));
      case 'price_desc':
        return arr.sort((a, b) => (b.combo_price ?? b.price) - (a.combo_price ?? a.price));
      case 'newest':
        return arr.sort((a, b) => {
          const da = a.created_at ? new Date(a.created_at).getTime() : 0;
          const db = b.created_at ? new Date(b.created_at).getTime() : 0;
          return db - da;
        });
      default:
        return arr;
    }
  }, [products, currentSort]);

  const handleSortChange = (value: string) => {
    const params = new URLSearchParams(searchParams.toString());
    if (value) {
      params.set('sort', value);
    } else {
      params.delete('sort');
    }
    router.push(`${pathname}?${params.toString()}`, { scroll: false });
  };

  return (
    <div>
      {/* Sort dropdown */}
      <div className="flex justify-end mb-6">
        <div className="relative">
          <select
            value={currentSort}
            onChange={(e) => handleSortChange(e.target.value)}
            className="appearance-none bg-white border border-gray-300 rounded-full px-5 py-2 pr-10 text-sm text-gray-700 cursor-pointer hover:border-[#9F6B3E] focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none transition-colors"
          >
            {SORT_OPTIONS.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth={2}
            stroke="currentColor"
            className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </div>
      </div>

      <ProductCardGrid products={sorted} staggerKey={staggerKey} />
    </div>
  );
}
