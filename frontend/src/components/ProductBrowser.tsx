'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import ProductCard from './ProductCard';
import { getProducts, type Product, type ProductCategory } from '@/lib/api';
import { categoryVisual } from '@/lib/category-visual';

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

  const totalCount = sorted.length;
  const activeCatObj = categories.find((c) => c.slug === category);
  const activeCatCount = activeCatObj?.products_count ?? null;

  return (
    <>
      {/* Category Pills — sticky bar with emoji + count badges */}
      {categories.length > 0 && (
        <div className="mb-4 -mx-4 sm:mx-0 sticky top-[64px] md:top-[80px] z-20 bg-white/90 backdrop-blur-md py-3 sm:py-4 border-b border-[#e7d9cb]/60">
          <div className="flex gap-2 px-4 sm:px-0 overflow-x-auto scrollbar-hide snap-x snap-mandatory">
            <button
              onClick={() => changeCategory('')}
              className={`shrink-0 snap-start inline-flex items-center gap-1.5 pl-4 pr-3 py-2 rounded-full text-sm font-black transition-all duration-300 cursor-pointer ${
                !category
                  ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105'
                  : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
              }`}
              style={{ opacity: 0, animation: 'pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) 0s forwards' }}
            >
              <span className="text-base">✨</span>
              全部
              {initialProducts.length > 0 && !category && (
                <span className="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full bg-white/20 text-[10px] font-black">
                  {initialProducts.length}
                </span>
              )}
            </button>
            {categories.map((cat, i) => {
              const v = categoryVisual(cat.name);
              const active = category === cat.slug;
              return (
                <button
                  key={cat.id}
                  onClick={() => changeCategory(cat.slug)}
                  className={`shrink-0 snap-start inline-flex items-center gap-1.5 pl-3 pr-3 py-2 rounded-full text-sm font-black transition-all duration-300 cursor-pointer ${
                    active
                      ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105'
                      : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
                  }`}
                  style={{ opacity: 0, animation: `pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) ${(i + 1) * 50}ms forwards` }}
                >
                  <span className="text-base">{v.emoji}</span>
                  {cat.name}
                  {cat.products_count !== undefined && cat.products_count > 0 && (
                    <span className={`inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-black ${
                      active ? 'bg-white/20' : 'bg-[#fdf7ef] text-[#9F6B3E]'
                    }`}>
                      {cat.products_count}
                    </span>
                  )}
                </button>
              );
            })}
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

      {/* Toolbar — count + active filter chip + sort (single row on desktop) */}
      <div className="flex items-center justify-between gap-3 mb-6">
        <div className="flex items-center gap-2 flex-wrap min-w-0">
          <span className="text-sm text-gray-600 shrink-0">
            共 <strong className="text-[#9F6B3E]">{totalCount}</strong> 件
          </span>
          {activeCatObj && (
            <button
              onClick={() => changeCategory('')}
              className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-[#fdf7ef] border border-[#e7d9cb] text-xs font-black text-[#7a5836] hover:bg-[#f7eee3] transition-colors"
              aria-label="清除分類篩選"
            >
              <span className="text-sm">{categoryVisual(activeCatObj.name).emoji}</span>
              {activeCatObj.name}
              {activeCatCount !== null && <span className="text-[#9F6B3E]">· {activeCatCount}</span>}
              <span className="ml-0.5 text-gray-400">✕</span>
            </button>
          )}
        </div>
        <div className="relative shrink-0">
          <select
            value={sort}
            onChange={(e) => changeSort(e.target.value as SortValue)}
            className="appearance-none bg-white border border-gray-300 rounded-full pl-4 pr-9 py-2 text-sm text-gray-700 cursor-pointer hover:border-[#9F6B3E] focus:ring-2 focus:ring-[#9F6B3E] focus:border-transparent outline-none transition-colors"
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
          className={`grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 transition-opacity ${
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
