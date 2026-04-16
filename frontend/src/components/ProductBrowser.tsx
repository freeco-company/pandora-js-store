'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ProductCard from './ProductCard';
import { getProducts, type Product, type ProductCategory } from '@/lib/api';

const SORT_OPTIONS = [
  { value: '', label: '預設排序' },
  { value: 'price_asc', label: '價格低到高' },
  { value: 'price_desc', label: '價格高到低' },
  { value: 'newest', label: '最新上架' },
] as const;

type SortValue = (typeof SORT_OPTIONS)[number]['value'];

interface Props {
  initialProducts: Product[];
  categories: ProductCategory[];
  initialCategory: string;
  initialSort: SortValue;
}

export default function ProductBrowser({
  initialProducts,
  categories,
  initialCategory,
  initialSort,
}: Props) {
  const [category, setCategory] = useState(initialCategory);
  const [sort, setSort] = useState<SortValue>(initialSort);
  const [products, setProducts] = useState<Product[]>(initialProducts);
  const [phase, setPhase] = useState<'idle' | 'out' | 'in'>('in');
  const reqId = useRef(0);

  // Entering /products from an external link (e.g. homepage category card)
  // should start at top of page — Next.js preserves scroll within the
  // same pathname which causes a mid-page landing when the referrer is
  // also scrolled down.
  useEffect(() => {
    if (typeof window !== 'undefined') {
      window.scrollTo({ top: 0, behavior: 'instant' as ScrollBehavior });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const sorted = useMemo(() => {
    const arr = [...products];
    switch (sort) {
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
  }, [products, sort]);

  const syncUrl = useCallback((nextCategory: string, nextSort: SortValue) => {
    const params = new URLSearchParams();
    if (nextCategory) params.set('category', nextCategory);
    if (nextSort) params.set('sort', nextSort);
    const qs = params.toString();
    const url = qs ? `/products?${qs}` : '/products';
    window.history.replaceState(null, '', url);
  }, []);

  const load = useCallback(async (nextCategory: string) => {
    const id = ++reqId.current;
    setPhase('out');
    await new Promise((r) => setTimeout(r, 180));
    if (reqId.current !== id) return;
    try {
      const result = await getProducts(nextCategory || undefined);
      if (reqId.current !== id) return;
      setProducts(result);
    } catch {
      if (reqId.current !== id) return;
      setProducts([]);
    }
    requestAnimationFrame(() => setPhase('in'));
  }, []);

  const changeCategory = (slug: string) => {
    if (slug === category) return;
    setCategory(slug);
    syncUrl(slug, sort);
    load(slug);
  };

  const changeSort = (value: SortValue) => {
    setSort(value);
    syncUrl(category, value);
  };

  useEffect(() => {
    const onPop = () => {
      const sp = new URLSearchParams(window.location.search);
      const c = sp.get('category') || '';
      const s = (sp.get('sort') || '') as SortValue;
      if (c !== category) {
        setCategory(c);
        setSort(s);
        load(c);
      } else if (s !== sort) {
        setSort(s);
      }
    };
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, [category, sort, load]);

  return (
    <>
      {/* Category Pills */}
      {categories.length > 0 && (
        <div className="mb-6 sm:mb-8 -mx-4 sm:mx-0 sticky top-[64px] md:top-[80px] z-20 bg-white/85 backdrop-blur-md py-3 sm:py-0 sm:bg-transparent sm:backdrop-blur-none sm:static">
          <div className="flex gap-2 px-4 sm:px-0 overflow-x-auto scrollbar-hide snap-x snap-mandatory">
            <button
              onClick={() => changeCategory('')}
              className={`shrink-0 snap-start px-5 py-2 rounded-full text-sm font-black transition-all duration-300 cursor-pointer ${
                !category
                  ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105'
                  : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
              }`}
              style={{
                opacity: 0,
                animation: 'pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) 0s forwards',
              }}
            >
              全部
            </button>
            {categories.map((cat, i) => (
              <button
                key={cat.id}
                onClick={() => changeCategory(cat.slug)}
                className={`shrink-0 snap-start px-5 py-2 rounded-full text-sm font-black transition-all duration-300 cursor-pointer ${
                  category === cat.slug
                    ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105'
                    : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
                }`}
                style={{
                  opacity: 0,
                  animation: `pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) ${(i + 1) * 50}ms forwards`,
                }}
              >
                {cat.name}
              </button>
            ))}
          </div>
          <style>{`
            @keyframes pill-in {
              from { opacity: 0; transform: translateY(8px); }
              to { opacity: 1; transform: translateY(0); }
            }
            @keyframes card-in {
              from { opacity: 0; transform: translateY(16px) scale(0.97); }
              to { opacity: 1; transform: translateY(0) scale(1); }
            }
            .pc-exit { opacity: 0; transform: translateY(-8px) scale(0.98); transition: opacity 180ms ease-out, transform 180ms ease-out; pointer-events: none; }
            .pc-enter { opacity: 0; animation: card-in 450ms cubic-bezier(0.2, 0.9, 0.3, 1.1) forwards; }
          `}</style>
        </div>
      )}

      {/* Sort dropdown */}
      <div className="flex justify-end mb-6">
        <div className="relative">
          <select
            value={sort}
            onChange={(e) => changeSort(e.target.value as SortValue)}
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

      {sorted.length > 0 ? (
        <div
          key={category || '__all__'}
          className={`grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-6 transition-opacity ${
            phase === 'out' ? 'opacity-0' : 'opacity-100'
          }`}
        >
          {sorted.map((product, i) => (
            <div
              key={product.id}
              className={phase === 'out' ? 'pc-exit' : 'pc-enter'}
              style={phase === 'in' ? { animationDelay: `${i * 55}ms` } : undefined}
            >
              <ProductCard product={product} />
            </div>
          ))}
        </div>
      ) : (
        <div
          className={`text-center py-20 transition-opacity duration-200 ${
            phase === 'out' ? 'opacity-0' : 'opacity-100'
          }`}
        >
          <div className="text-5xl mb-4">🛍️</div>
          <p className="text-base font-black text-gray-700">這個分類還在準備中</p>
          <p className="text-sm text-gray-500 mt-2">先看看其他分類吧 ✨</p>
        </div>
      )}
    </>
  );
}
