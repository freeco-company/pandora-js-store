'use client';

import Link from 'next/link';
import Image from 'next/image';
import { useCallback, useEffect, useRef, useState, useTransition } from 'react';
import { getArticles, imageUrl, type Article } from '@/lib/api';
import { ARTICLE_TABS, ARTICLE_TYPE_LABEL as TYPE_LABEL } from '@/lib/article-tabs';

interface Props {
  initialArticles: Article[];
  initialType: string;
  initialLastPage: number;
  initialPage: number;
}

export default function ArticleBrowser({
  initialArticles,
  initialType,
  initialLastPage,
  initialPage,
}: Props) {
  const [type, setType] = useState(initialType);
  const [articles, setArticles] = useState<Article[]>(initialArticles);
  const [page, setPage] = useState(initialPage);
  const [lastPage, setLastPage] = useState(initialLastPage);
  const [phase, setPhase] = useState<'idle' | 'out' | 'in'>('in');
  const [, startTransition] = useTransition();
  const reqId = useRef(0);

  const load = useCallback(async (nextType: string, nextPage: number) => {
    const id = ++reqId.current;
    setPhase('out');
    // Let exit animation play briefly
    await new Promise((r) => setTimeout(r, 180));
    if (reqId.current !== id) return;
    try {
      const result = await getArticles(nextType || undefined, nextPage);
      if (reqId.current !== id) return;
      setArticles(result.data);
      setLastPage(result.last_page);
    } catch {
      if (reqId.current !== id) return;
      setArticles([]);
      setLastPage(1);
    }
    // Trigger enter on next frame
    requestAnimationFrame(() => setPhase('in'));
  }, []);

  const changeTab = (key: string) => {
    if (key === type) return;
    setType(key);
    setPage(1);
    // Update URL without full reload
    const params = new URLSearchParams();
    if (key) params.set('type', key);
    const qs = params.toString();
    const url = qs ? `/articles?${qs}` : '/articles';
    startTransition(() => {
      window.history.replaceState(null, '', url);
    });
    load(key, 1);
  };

  const goPage = (p: number) => {
    if (p === page) return;
    setPage(p);
    const params = new URLSearchParams();
    if (type) params.set('type', type);
    if (p > 1) params.set('page', String(p));
    const qs = params.toString();
    const url = qs ? `/articles?${qs}` : '/articles';
    window.history.replaceState(null, '', url);
    load(type, p);
    // Scroll to top of grid
    document.getElementById('article-grid-top')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  // Handle browser back/forward
  useEffect(() => {
    const onPop = () => {
      const sp = new URLSearchParams(window.location.search);
      const t = sp.get('type') || '';
      const p = parseInt(sp.get('page') || '1', 10);
      if (t !== type || p !== page) {
        setType(t);
        setPage(p);
        load(t, p);
      }
    };
    window.addEventListener('popstate', onPop);
    return () => window.removeEventListener('popstate', onPop);
  }, [type, page, load]);

  const activeTab = ARTICLE_TABS.find((t) => t.key === type);

  return (
    <>
      {/* Tabs */}
      <div className="mb-6 sm:mb-8 -mx-4 sm:mx-0 sticky top-[64px] md:top-[80px] z-20 bg-white/85 backdrop-blur-md py-3 sm:py-0 sm:bg-transparent sm:backdrop-blur-none sm:static">
        <div className="flex gap-2 px-4 sm:px-0 overflow-x-auto scrollbar-hide snap-x snap-mandatory">
          {ARTICLE_TABS.map((tab, i) => {
            const active = type === tab.key;
            return (
              <button
                key={tab.key || 'all'}
                onClick={() => changeTab(tab.key)}
                className={`shrink-0 snap-start px-4 py-2 rounded-full text-sm font-black transition-all duration-300 inline-flex items-center gap-1.5 cursor-pointer ${
                  active
                    ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md shadow-[#9F6B3E]/30 scale-105'
                    : 'bg-white border border-[#e7d9cb] text-gray-700 hover:border-[#9F6B3E] hover:text-[#9F6B3E]'
                }`}
                style={{
                  opacity: 0,
                  animation: `pill-in 0.4s cubic-bezier(0.2, 0.9, 0.3, 1.1) ${i * 50}ms forwards`,
                }}
              >
                <span className="text-base">{tab.emoji}</span>
                {tab.label}
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
      </div>

      <div id="article-grid-top" className="scroll-mt-28" />

      {articles.length > 0 ? (
        <div
          key={`${type}-${page}`}
          className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6 transition-opacity ${
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
                  <Image
                    src={imageUrl(article.featured_image)!}
                    alt={article.title}
                    fill
                    sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                    className="object-cover transition-transform duration-[800ms] ease-[cubic-bezier(0.2,0.9,0.3,1)] group-hover:scale-[1.08]"
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center text-[#9F6B3E]/40 text-5xl">📖</div>
                )}
                <span className="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-white/90 backdrop-blur text-[10px] font-black text-[#9F6B3E] shadow-sm">
                  {TYPE_LABEL[article.source_type] || '文章'}
                </span>
              </div>
              <div className="p-4 sm:p-5">
                <time className="text-[11px] text-gray-400 font-bold tracking-wide">
                  {new Date(article.published_at).toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric' })}
                </time>
                <h3 className="font-black text-gray-900 mt-1.5 mb-2 line-clamp-2 group-hover:text-[#9F6B3E] transition-colors leading-snug">
                  {article.title}
                </h3>
                {article.excerpt && (
                  <p className="text-sm text-gray-500 line-clamp-2 leading-relaxed">{article.excerpt}</p>
                )}
                <div className="mt-3 text-[12px] font-black text-[#9F6B3E] inline-flex items-center gap-1">
                  閱讀全文
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
          <div className="text-5xl mb-4">📭</div>
          <p className="text-base font-black text-gray-700">這個分類還在準備中</p>
          <p className="text-sm text-gray-500 mt-2">先看看其他分類吧 ✨</p>
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
