'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState, useTransition } from 'react';
import { getArticles, imageUrl, type Article } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import { ARTICLE_TABS, ARTICLE_TYPE_LABEL as TYPE_LABEL } from '@/lib/article-tabs';
import CategoryPills, { type PillItem } from './CategoryPills';

interface Props {
  initialArticles: Article[];
  initialType: string;
  initialLastPage: number;
  initialPage: number;
  initialCategory?: string;
  /** Keys of ARTICLE_TABS that have at least 1 article. When provided, empty tabs are hidden. */
  liveTabKeys?: string[];
  /** Subcategory slugs that have at least 1 article. When provided, empty subs are hidden. */
  liveSubcategoryKeys?: string[];
}

export default function ArticleBrowser({
  initialArticles,
  initialType,
  initialLastPage,
  initialPage,
  initialCategory = '',
  liveTabKeys,
  liveSubcategoryKeys,
}: Props) {
  const [type, setType] = useState(initialType);
  const [category, setCategory] = useState(initialCategory);
  const [articles, setArticles] = useState<Article[]>(initialArticles);
  const [page, setPage] = useState(initialPage);
  const [lastPage, setLastPage] = useState(initialLastPage);
  const [phase, setPhase] = useState<'idle' | 'out' | 'in'>('in');
  const [, startTransition] = useTransition();
  const reqId = useRef(0);

  const load = useCallback(async (nextType: string, nextPage: number, nextCategory?: string) => {
    const id = ++reqId.current;
    setPhase('out');
    await new Promise((r) => setTimeout(r, 180));
    if (reqId.current !== id) return;
    try {
      const result = await getArticles(nextType || undefined, nextPage, 12, nextCategory || undefined);
      if (reqId.current !== id) return;
      setArticles(result.data);
      setLastPage(result.last_page);
    } catch {
      if (reqId.current !== id) return;
      setArticles([]);
      setLastPage(1);
    }
    requestAnimationFrame(() => setPhase('in'));
  }, []);

  const updateUrl = useCallback((t: string, p: number, cat: string) => {
    const params = new URLSearchParams();
    if (t) params.set('type', t);
    if (cat) params.set('category', cat);
    if (p > 1) params.set('page', String(p));
    const qs = params.toString();
    const url = qs ? `/articles?${qs}` : '/articles';
    window.history.replaceState(null, '', url);
  }, []);

  const changeTab = (key: string) => {
    if (key === type) return;
    setType(key);
    setCategory('');
    setPage(1);
    startTransition(() => updateUrl(key, 1, ''));
    load(key, 1, '');
  };

  const changeCategory = (cat: string) => {
    if (cat === category) return;
    setCategory(cat);
    setPage(1);
    startTransition(() => updateUrl(type, 1, cat));
    load(type, 1, cat);
  };

  const goPage = (p: number) => {
    if (p === page) return;
    setPage(p);
    updateUrl(type, p, category);
    load(type, p, category);
    document.getElementById('article-grid-top')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  // Handle browser back/forward
  useEffect(() => {
    const onPop = () => {
      const sp = new URLSearchParams(window.location.search);
      const t = sp.get('type') || '';
      const p = parseInt(sp.get('page') || '1', 10);
      const cat = sp.get('category') || '';
      if (t !== type || p !== page || cat !== category) {
        setType(t);
        setPage(p);
        setCategory(cat);
        load(t, p, cat);
      }
    };
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, [type, page, category, load]);

  const activeTab = ARTICLE_TABS.find((t) => t.key === type);
  const subcategories = activeTab?.subcategories;

  const pillItems: PillItem[] = useMemo(() => {
    const live = liveTabKeys ? new Set(liveTabKeys) : null;
    return ARTICLE_TABS
      .filter((t) => t.key === '' || !live || live.has(t.key))
      .map((t) => ({ key: t.key, label: t.label, icon: t.icon, iconColor: t.iconColor }));
  }, [liveTabKeys]);

  const subPillItems: PillItem[] | null = useMemo(() => {
    if (!subcategories || subcategories.length === 0) return null;
    const liveSubs = liveSubcategoryKeys ? new Set(liveSubcategoryKeys) : null;
    const visible = subcategories.filter((s) => !liveSubs || liveSubs.has(s.key));
    if (visible.length === 0) return null;
    return [
      { key: '', label: '全部' },
      ...visible.map((s) => ({ key: s.key, label: s.label })),
    ];
  }, [subcategories, liveSubcategoryKeys]);

  return (
    <>
      <CategoryPills items={pillItems} activeKey={type} onChange={changeTab} />
      {subPillItems && (
        <div className="-mt-2 mb-5">
          <div className="flex flex-wrap gap-2">
            {subPillItems.map((item) => {
              const active = category === item.key;
              return (
                <button
                  key={item.key || '__sub_all__'}
                  onClick={() => changeCategory(item.key)}
                  className={`min-h-[44px] px-5 py-2.5 rounded-full text-sm font-medium transition-all duration-200 cursor-pointer ${
                    active
                      ? 'bg-[#9F6B3E]/15 text-[#9F6B3E] font-bold border-2 border-[#9F6B3E]/40 shadow-sm'
                      : 'bg-white text-gray-600 border border-[#e7d9cb] hover:text-[#9F6B3E] hover:border-[#9F6B3E]/40'
                  }`}
                >
                  {item.label}
                </button>
              );
            })}
          </div>
        </div>
      )}
      <style>{`
        @keyframes card-in {
          from { opacity: 0; transform: translateY(16px) scale(0.97); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .card-exit {
          opacity: 0;
          transform: translateY(-8px) scale(0.98);
          transition: opacity 180ms ease-out, transform 180ms ease-out;
          pointer-events: none;
        }
        .card-enter {
          opacity: 0;
          animation: card-in 450ms cubic-bezier(0.2, 0.9, 0.3, 1.1) forwards;
        }
      `}</style>

      <div id="article-grid-top" className="scroll-mt-28" />

      {articles.length > 0 ? (
        <div
          key={`${type}-${category}-${page}`}
          className={`grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 sm:gap-5 transition-opacity ${
            phase === 'out' ? 'opacity-0' : 'opacity-100'
          }`}
        >
          {articles.map((article, i) => (
            <Link
              key={article.id}
              href={`/articles/${article.slug}`}
              data-cursor="card"
              data-cursor-label="閱讀"
              className={`group bg-white rounded-3xl border border-[#e7d9cb] overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-400 ${
                phase === 'out' ? 'card-exit' : 'card-enter'
              }`}
              style={phase === 'in' ? { animationDelay: `${i * 55}ms` } : undefined}
            >
              <div className="relative aspect-[16/10] bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] overflow-hidden">
                {article.featured_image ? (
                  <ImageWithFallback
                    src={imageUrl(article.featured_image)!}
                    alt={article.title}
                    fill
                    sizes="(max-width: 640px) 50vw, (max-width: 768px) 50vw, (max-width: 1024px) 33vw, 25vw"
                    className="object-cover transition-transform duration-[800ms] ease-[cubic-bezier(0.2,0.9,0.3,1)] group-hover:scale-[1.08]"
                  />
                ) : (
                  <LogoPlaceholder />
                )}
                <span className="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-white/90 backdrop-blur text-[10px] font-black text-[#9F6B3E] shadow-sm">
                  {TYPE_LABEL[article.source_type] || '文章'}
                </span>
              </div>
              <div className="p-3 sm:p-4">
                <time className="text-[10px] text-gray-400 font-bold tracking-wide">
                  {new Date(article.published_at).toLocaleDateString('zh-TW', { year: 'numeric', month: 'short', day: 'numeric' })}
                </time>
                <h3 className="font-black text-gray-900 text-sm sm:text-base mt-1 mb-1.5 line-clamp-2 group-hover:text-[#9F6B3E] transition-colors leading-snug">
                  {article.title}
                </h3>
                <p className="text-xs text-gray-400 line-clamp-2 leading-relaxed hidden sm:block">{article.excerpt || ''}</p>
                <div className="mt-2 text-[11px] font-black text-[#9F6B3E] inline-flex items-center gap-1">
                  閱讀
                  <span className="transition-transform duration-300 group-hover:translate-x-1">→</span>
                </div>
              </div>
            </Link>
          ))}
        </div>
      ) : (
        <div
          className={`text-center py-20 bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl border border-[#e7d9cb] transition-opacity duration-200 ${
            phase === 'out' ? 'opacity-0' : 'opacity-100'
          }`}
        >
          <svg className="mx-auto w-16 h-16 mb-4 text-[#9F6B3E]/25" viewBox="0 0 48 48" fill="none" aria-hidden>
            <rect x="8" y="16" width="32" height="22" rx="3" stroke="currentColor" strokeWidth="2" />
            <path d="M8 22l16 10 16-10" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
            <path d="M20 16V10a4 4 0 018 0v6" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
          </svg>
          <p className="text-base font-black text-gray-700">這個分類還在準備中</p>
          <p className="text-sm text-gray-500 mt-2">先看看其他分類吧</p>
        </div>
      )}

      {lastPage > 1 && (
        <nav className="flex justify-center items-center gap-1.5 sm:gap-2 mt-10 flex-wrap" aria-label="文章分頁">
          {page > 1 && (
            <button
              onClick={() => goPage(page - 1)}
              className="px-4 h-10 inline-flex items-center rounded-full border border-[#e7d9cb] text-sm font-bold text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E] transition-colors bg-white cursor-pointer"
            >
              ← 上一頁
            </button>
          )}
          {Array.from({ length: lastPage }, (_, i) => i + 1).slice(0, 7).map((p) => (
            <button
              key={p}
              onClick={() => goPage(p)}
              className={`w-10 h-10 flex items-center justify-center rounded-full text-sm font-black transition-all cursor-pointer ${
                p === page
                  ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-110'
                  : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
              }`}
            >
              {p}
            </button>
          ))}
          {page < lastPage && (
            <button
              onClick={() => goPage(page + 1)}
              className="px-4 h-10 inline-flex items-center rounded-full border border-[#e7d9cb] text-sm font-bold text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E] transition-colors bg-white cursor-pointer"
            >
              下一頁 →
            </button>
          )}
        </nav>
      )}
    </>
  );
}
