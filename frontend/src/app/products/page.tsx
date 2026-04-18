import type { Metadata } from 'next';
import { getProducts, getProductCategories, imageUrl } from '@/lib/api';
import ProductBrowser from '@/components/ProductBrowser';
import ScrollReveal from '@/components/ScrollReveal';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';
import TextReveal from '@/components/TextReveal';
import FloatingShapes from '@/components/FloatingShapes';
import LogoLoader from '@/components/LogoLoader';

export const revalidate = 3600;

export const metadata: Metadata = {
  title: '全館商品',
  description:
    'JEROSSE 婕樂纖全系列商品一覽：保健食品、美容保養、體重管理、葉黃素、益生菌、口服玻尿酸。任選兩件享 1+1 搭配價，滿 NT$4,000 全車自動升級 VIP 最低價。官方正品授權，安心選購。',
  alternates: { canonical: '/products' },
  openGraph: {
    title: '全館商品｜婕樂纖仙女館',
    description:
      'JEROSSE 婕樂纖全系列商品一覽：保健食品、美容保養、體重管理、葉黃素、益生菌、口服玻尿酸。任選兩件享搭配價，滿額再享 VIP 優惠。',
  },
};

type SortValue = '' | 'price_asc' | 'price_desc' | 'newest';

export default async function ProductsPage({
  searchParams,
}: {
  searchParams: Promise<{ category?: string; sort?: string }>;
}) {
  const { category, sort } = await searchParams;
  const initialCategory = category || '';
  const initialSort = (['price_asc', 'price_desc', 'newest'].includes(sort || '') ? sort : '') as SortValue;

  let products: Awaited<ReturnType<typeof getProducts>> = [];
  let categories: Awaited<ReturnType<typeof getProductCategories>> = [];

  try {
    products = await getProducts(initialCategory || undefined);
  } catch {}
  try {
    categories = await getProductCategories();
  } catch {}

  const activeCatName = initialCategory
    ? categories.find((c) => c.slug === initialCategory)?.name || '商品'
    : null;

  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '全館商品', url: activeCatName ? '/products' : undefined },
    ...(activeCatName ? [{ name: activeCatName }] : []),
  ]);

  return (
    <div className="relative">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbs, {
          '@context': 'https://schema.org',
          '@type': 'ItemList',
          name: activeCatName || '全館商品',
          numberOfItems: products.length,
          itemListElement: products.slice(0, 30).map((p, i) => ({
            '@type': 'ListItem',
            position: i + 1,
            url: `${siteUrl}/products/${p.slug}`,
            name: p.name,
            image: p.image ? imageUrl(p.image) : undefined,
          })),
        }) }}
      />
      {/* Hero */}
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
                COLLECTION · {activeCatName || '全系列'}
              </span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text={activeCatName || '全館商品'}
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-lg mx-auto text-balance leading-relaxed">
              任選 2 件享 <span className="font-black text-[#9F6B3E]">1+1 搭配價</span>
              <span className="mx-1.5 text-gray-400">·</span>
              組合滿 $4,000 升級 <span className="font-black text-[#9F6B3E]">VIP 優惠價</span>
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>
      </section>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10">
        {products.length > 0 || categories.length > 0 ? (
          <ProductBrowser
            initialProducts={products}
            categories={categories}
            initialCategory={initialCategory}
            initialSort={initialSort}
          />
        ) : (
          <div className="text-center py-20">
            <div className="flex justify-center mb-4">
              <LogoLoader size={72} />
            </div>
            <p className="text-sm text-gray-400">目前沒有商品</p>
          </div>
        )}
      </div>
    </div>
  );
}
