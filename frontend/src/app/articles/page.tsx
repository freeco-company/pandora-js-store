import type { Metadata } from 'next';
import { getArticles } from '@/lib/api';
import ArticleBrowser from '@/components/ArticleBrowser';
import { ARTICLE_TABS } from '@/lib/article-tabs';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import FloatingShapes from '@/components/FloatingShapes';

export const revalidate = 3600;

export const metadata: Metadata = {
  title: '最新文章',
  description: '婕樂纖誌 — 營養師專欄、媒體報導、品牌故事與真實口碑。',
  alternates: { canonical: '/articles' },
};

export default async function ArticlesPage({
  searchParams,
}: {
  searchParams: Promise<{ type?: string; page?: string; category?: string }>;
}) {
  const { type, page, category } = await searchParams;
  const currentPage = parseInt(page || '1', 10);
  const initialType = type || '';
  const initialCategory = category || '';

  // Collect all subcategory slugs across all tabs
  const allSubcategories = ARTICLE_TABS
    .flatMap((t) => (t.subcategories ?? []).map((s) => ({ type: t.key, slug: s.key })));

  // Fetch current page + per-type totals + subcategory totals in parallel
  const typeKeys = ARTICLE_TABS.filter((t) => t.key !== '').map((t) => t.key);
  const [mainResult, ...restCounts] = await Promise.all([
    getArticles(initialType || undefined, currentPage, 12, initialCategory || undefined).catch(() => null),
    ...typeKeys.map((k) => getArticles(k, 1, 1).then((r) => r.total).catch(() => 0)),
    ...allSubcategories.map((s) => getArticles(s.type, 1, 1, s.slug).then((r) => r.total).catch(() => 0)),
  ]);

  const typeCounts = restCounts.slice(0, typeKeys.length) as number[];
  const subCounts = restCounts.slice(typeKeys.length) as number[];

  const initialArticles = mainResult?.data ?? [];
  const initialLastPage = mainResult?.last_page ?? 1;

  // Only include tab keys that have articles
  const liveTabKeys = typeKeys.filter((_, i) => typeCounts[i] > 0);

  // Only include subcategory keys that have articles
  const liveSubcategoryKeys = allSubcategories
    .filter((_, i) => subCounts[i] > 0)
    .map((s) => s.slug);

  const activeTab = ARTICLE_TABS.find((t) => t.key === initialType);

  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '專欄文章', url: activeTab?.key ? '/articles' : undefined },
    ...(activeTab?.key ? [{ name: activeTab.label }] : []),
  ]);

  return (
    <div className="relative">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbs) }}
      />
      <section
        className="relative overflow-hidden"
        style={{
          background:
            'radial-gradient(ellipse at 20% 30%, #f7c79a22 0%, transparent 50%),' +
            'radial-gradient(ellipse at 80% 70%, #e7a77e22 0%, transparent 50%),' +
            'linear-gradient(135deg, #e7d9cb 0%, #efe2d1 50%, #e7d9cb 100%)',
        }}
      >
        <FloatingShapes />
        <div className="relative max-w-7xl mx-auto px-5 sm:px-6 lg:px-8 py-14 sm:py-20 text-center">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-4 shadow-sm">
              <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
              <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">
                ARTICLES · {activeTab?.label || '全部'}
              </span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text={activeTab?.label || '婕樂纖誌'}
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-lg mx-auto">
              專業營養師撰寫、媒體專訪、品牌故事、真實口碑 — 仙女生活全紀錄
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>
      </section>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
        <ArticleBrowser
          initialArticles={initialArticles}
          initialType={initialType}
          initialLastPage={initialLastPage}
          initialPage={currentPage}
          initialCategory={initialCategory}
          liveTabKeys={liveTabKeys}
          liveSubcategoryKeys={liveSubcategoryKeys}
        />
      </div>
    </div>
  );
}
