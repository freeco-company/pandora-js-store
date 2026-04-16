import type { Metadata } from 'next';
import Image from 'next/image';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { getArticle, getArticles, getProducts, imageUrl } from '@/lib/api';
import ShareButtons from '@/components/ShareButtons';
import ProductCardGrid from '@/components/ProductCardGrid';
import { sanitizeHtml } from '@/lib/sanitize';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 3600;

type Props = {
  params: Promise<{ slug: string }>;
};

/**
 * Pre-render every published article at build time for instant LCP.
 * Unknown slugs fall through to ISR (dynamicParams defaults to true).
 */
export async function generateStaticParams() {
  try {
    // Fetch in pages of 100 (current: ~387 articles → ~4 requests)
    const first = await getArticles(undefined, 1, 100);
    let all = first.data;
    for (let page = 2; page <= Math.min(first.last_page, 20); page++) {
      try {
        const next = await getArticles(undefined, page, 100);
        all = all.concat(next.data);
      } catch {
        break;
      }
    }
    return all.map((a) => ({ slug: a.slug }));
  } catch {
    return [];
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  try {
    const article = await getArticle(slug);
    return {
      title: article.seo_meta?.title || article.title,
      description: article.seo_meta?.description || article.excerpt,
      openGraph: {
        title: article.title,
        description: article.excerpt,
        type: 'article',
        publishedTime: article.published_at,
        images: article.seo_meta?.og_image
          ? [imageUrl(article.seo_meta.og_image)!]
          : article.featured_image
            ? [imageUrl(article.featured_image)!]
            : [],
      },
    };
  } catch {
    return { title: '文章不存在' };
  }
}

export default async function ArticleDetailPage({ params }: Props) {
  const { slug } = await params;

  let article: Awaited<ReturnType<typeof getArticle>> | null = null;
  try {
    article = await getArticle(slug);
  } catch {
    // fall through
  }
  if (!article) {
    notFound();
  }

  // Fetch related articles
  let relatedArticles: Awaited<ReturnType<typeof getArticles>>['data'] = [];
  try {
    const result = await getArticles(article.source_type, 1);
    relatedArticles = result.data.filter((a) => a.id !== article.id).slice(0, 3);
  } catch {
    // Ignore
  }

  // Fetch recommended products for content-to-commerce
  let recommendedProducts: Awaited<ReturnType<typeof getProducts>> = [];
  try {
    const allProducts = await getProducts();
    // Pick 3 random products
    recommendedProducts = allProducts
      .sort(() => Math.random() - 0.5)
      .slice(0, 3);
  } catch {
    // Ignore
  }

  const sourceLabel =
    article.source_type === 'blog'
      ? '婕樂纖誌'
      : article.source_type === 'news'
        ? '媒體報導'
        : article.source_type === 'brand'
          ? '品牌事蹟'
          : article.source_type === 'recommend'
            ? '口碑推薦'
            : '文章';

  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora-dev.js-store.com.tw';
  const articleJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: article.title,
    description: article.excerpt || article.title,
    image: article.featured_image ? imageUrl(article.featured_image) : undefined,
    datePublished: article.published_at,
    author: { '@type': 'Organization', name: '婕樂纖仙女館' },
    publisher: {
      '@type': 'Organization',
      name: '婕樂纖仙女館',
      logo: { '@type': 'ImageObject', url: `${siteUrl}/favicon.svg` },
    },
    mainEntityOfPage: { '@type': 'WebPage', '@id': `${siteUrl}/articles/${article.slug}` },
  };
  const typeLabel =
    article.source_type === 'blog' || article.source_type === 'news'
      ? '婕樂纖誌'
      : article.source_type === 'brand'
        ? '品牌事蹟'
        : article.source_type === 'recommend'
          ? '口碑推薦'
          : '文章';
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '專欄文章', url: '/articles' },
    { name: typeLabel, url: `/articles?type=${article.source_type === 'news' ? 'blog,news' : article.source_type}` },
    { name: article.title },
  ]);

  return (
    <>
    <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(articleJsonLd, breadcrumbs) }} />
    <article className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
      {/* Breadcrumb */}
      <nav className="mb-6 text-sm text-gray-500">
        <Link href="/articles" className="hover:text-[#9F6B3E] transition-colors">
          文章列表
        </Link>
        <span className="mx-2">/</span>
        <span className="text-gray-900">{article.title}</span>
      </nav>

      {/* Header */}
      <header className="mb-8">
        <div className="flex items-center gap-3 mb-4">
          <span className="text-xs font-medium text-[#9F6B3E] bg-[#9F6B3E]/10 px-2 py-1 rounded-full">
            {sourceLabel}
          </span>
          <time className="text-sm text-gray-400">
            {new Date(article.published_at).toLocaleDateString('zh-TW', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            })}
          </time>
        </div>
        <h1 className="text-3xl sm:text-4xl font-bold text-gray-900 leading-tight">
          {article.title}
        </h1>
        {article.excerpt && (
          <p className="mt-4 text-lg text-gray-600 leading-relaxed">
            {article.excerpt}
          </p>
        )}
      </header>

      {/* Featured Image */}
      {article.featured_image && (
        <div className="relative aspect-[16/9] bg-gray-50 rounded-xl overflow-hidden mb-8">
          <Image
            src={imageUrl(article.featured_image)!}
            alt={article.title}
            fill
            sizes="(max-width: 896px) 100vw, 896px"
            className="object-cover"
            priority
          />
        </div>
      )}

      {/* Content */}
      <div
        className="prose-article"
        dangerouslySetInnerHTML={{ __html: sanitizeHtml(article.content) }}
      />

      {/* Recommended Products (Content-to-Commerce) */}
      {recommendedProducts.length > 0 && (
        <section className="mt-16 pt-8 border-t border-gray-200">
          <h2 className="text-xl font-bold text-gray-900 mb-6">精選推薦商品</h2>
          <ProductCardGrid products={recommendedProducts} />
        </section>
      )}

      {/* Share */}
      <ShareButtons
        url={`${process.env.NEXT_PUBLIC_SITE_URL || 'https://shop.jerosse.tw'}/articles/${article.slug}`}
        title={article.title}
      />

      {/* Related Articles */}
      {relatedArticles.length > 0 && (
        <section className="mt-16 pt-8 border-t border-gray-200">
          <h2 className="text-xl font-bold text-gray-900 mb-6">相關文章</h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            {relatedArticles.map((related) => (
              <Link
                key={related.id}
                href={`/articles/${related.slug}`}
                className="group"
              >
                <div className="relative aspect-[16/9] bg-gray-50 rounded-lg overflow-hidden mb-3">
                  {related.featured_image ? (
                    <Image
                      src={imageUrl(related.featured_image)!}
                      alt={related.title}
                      fill
                      sizes="(max-width: 640px) 100vw, 280px"
                      className="object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-gray-300">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-8 h-8">
                        <path strokeLinecap="round" strokeLinejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5" />
                      </svg>
                    </div>
                  )}
                </div>
                <h3 className="font-medium text-gray-900 line-clamp-2 group-hover:text-[#9F6B3E] transition-colors">
                  {related.title}
                </h3>
              </Link>
            ))}
          </div>
        </section>
      )}
    </article>
    </>
  );
}
