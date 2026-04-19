import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getProducts, getProductCategories, imageUrl } from '@/lib/api';
import type { Product } from '@/lib/api';
import ProductBrowser from '@/components/ProductBrowser';
import { breadcrumbSchema, collectionPageSchema, jsonLdScript } from '@/lib/jsonld';
import { SITE_URL } from '@/lib/site';

export const revalidate = 3600;

const siteUrl = SITE_URL;

// SEO metadata per category
const categoryMeta: Record<string, { title: string; h1: string; description: string; intro: string }> = {
  'healthy-vitality-series': {
    title: '健康活力系列｜婕樂纖纖飄錠・纖纖飲X・爆纖錠',
    h1: '健康活力系列',
    description: '婕樂纖健康活力系列：纖飄錠（國家健康食品認證）、纖纖飲X（世界品質金獎）、爆纖錠、肽可可、厚焙奶茶。促進新陳代謝，維持健康體態。官方授權正品，任選兩件享搭配價。',
    intro: '由專業醫學團隊研發，多款產品榮獲國家健康食品認證與世界品質標章。搭配均衡飲食與適度運動，幫助維持健康體態與活力。',
  },
  'functional-health-series': {
    title: '健康維持系列｜婕樂纖益生菌・葉黃素・固樂纖',
    h1: '健康維持系列',
    description: '婕樂纖健康維持系列：高機能益生菌（15種菌種）、金盞花葉黃素晶亮凍、固樂纖DKKflex™、療肺草正冠茶、9國英雄極速錠。全家人的健康守護。官方授權正品。',
    intro: '從腸道健康到關節保養，嚴選天然成分，全家大小都適用。每日輕鬆補充，為健康打好基礎。',
  },
  'body-beauty-series': {
    title: '美容美體系列｜婕樂纖水光錠・面膜・婕肌零',
    h1: '美容美體系列',
    description: '婕樂纖美容美體系列：水光錠（日本專利玻尿酸）、水光繃帶面膜、婕肌零洗卸凝膠、雪聚露精華、急救小白瓶、法樂蓬洗髮露。由內而外的美麗保養。官方授權正品。',
    intro: '從口服美容到外用保養，結合日本、法國、韓國頂級原料，打造由內而外的完整美麗方案。',
  },
  'slimming': {
    title: '體重管理｜婕樂纖纖飄錠・爆纖錠・纖纖飲X 推薦',
    h1: '體重管理',
    description: '婕樂纖體重管理推薦：纖飄錠（健康食品認證不易形成體脂肪）、爆纖錠（促進新陳代謝）、纖纖飲X（代謝小綠）。搭配運動與均衡飲食，維持理想體態。',
    intro: '國家認證與世界品質肯定。選擇適合自己的纖體方案，搭配健康生活習慣，輕鬆維持理想體態。',
  },
  'health': {
    title: '健康保健｜婕樂纖益生菌・葉黃素果凍・固樂纖',
    h1: '健康保健',
    description: '婕樂纖健康保健食品：高機能益生菌調整體質、金盞花葉黃素晶亮凍護眼、固樂纖關節保養、療肺草正冠茶。天然成分，全家適用。官方授權正品。',
    intro: '嚴選天然植萃與專利成分，照顧全家人的每日健康需求。從腸道保健到關節養護，一站購足。',
  },
  'beauty': {
    title: '美容保養｜婕樂纖水光錠・面膜・洗卸凝膠・精華油',
    h1: '美容保養',
    description: '婕樂纖美容保養：水光錠口服玻尿酸、水光繃帶面膜極致補水、婕肌零三合一洗卸、急救小白瓶身體精華油、法樂蓬養髮。內外兼顧的保養方案。',
    intro: '結合口服美容與外用保養，運用日本 Hyabest 專利玻尿酸、法國繃帶普拉斯™等頂級原料，打造完整美肌計劃。',
  },
};

export async function generateStaticParams() {
  try {
    const categories = await getProductCategories();
    return categories.map((c) => ({ slug: c.slug }));
  } catch {
    return [];
  }
}

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  const meta = categoryMeta[slug];
  if (!meta) return { title: '商品分類' };
  return {
    title: meta.title,
    description: meta.description,
    alternates: { canonical: `/products/category/${slug}` },
    openGraph: {
      title: meta.title + '｜婕樂纖仙女館',
      description: meta.description,
    },
  };
}

export default async function CategoryPage({ params }: Props) {
  const { slug } = await params;
  const meta = categoryMeta[slug];
  if (!meta) notFound();

  const [productsRes, categoriesRes] = await Promise.allSettled([
    getProducts(slug),
    getProductCategories(),
  ]);
  const products: Product[] = productsRes.status === 'fulfilled' ? productsRes.value : [];
  const categories: Awaited<ReturnType<typeof getProductCategories>> =
    categoriesRes.status === 'fulfilled' ? categoriesRes.value : [];

  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '全館商品', url: '/products' },
    { name: meta.h1 },
  ]);

  const itemList = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    name: meta.h1,
    numberOfItems: products.length,
    itemListElement: products.slice(0, 30).map((p, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      url: `${siteUrl}/products/${p.slug}`,
      name: p.name,
      image: p.image ? imageUrl(p.image) : undefined,
    })),
  };
  const collectionPage = collectionPageSchema({
    url: `/products/category/${slug}`,
    name: meta.h1,
    description: meta.description,
    numberOfItems: products.length,
  });

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(collectionPage, breadcrumbs, itemList) }}
      />
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {/* Category header with SEO-rich intro text */}
        <div className="text-center mb-8">
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-2">CATEGORY · 商品分類</div>
          <h1 className="text-3xl sm:text-4xl font-black text-[#9F6B3E] mb-3">{meta.h1}</h1>
          <p className="text-sm text-gray-600 max-w-xl mx-auto leading-relaxed">{meta.intro}</p>
          <p className="text-xs text-gray-400 mt-2">
            任選 2 件享 <span className="text-[#9F6B3E] font-bold">1+1 搭配價</span> · 滿 $4,000 升級 <span className="text-[#9F6B3E] font-bold">VIP 優惠價</span>
          </p>
        </div>

        <ProductBrowser
          initialProducts={products}
          categories={categories}
          initialCategory={slug}
          initialSort=""
        />
      </div>
    </>
  );
}
