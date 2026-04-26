import Link from 'next/link';
import { getArticles, imageUrl, type RelatedArticleSummary } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';

/**
 * "相關閱讀" strip on the product detail page.
 *
 * If `articles` is supplied (from product.related_articles — fed by the
 * article_product pivot), uses those. Otherwise falls back to the most
 * recent 婕樂纖誌 (blog+news) articles so the slot is never empty.
 */
export default async function RelatedArticles({
  articles: provided,
}: {
  articles?: RelatedArticleSummary[];
} = {}) {
  let articles: Array<RelatedArticleSummary | Awaited<ReturnType<typeof getArticles>>['data'][number]> =
    provided && provided.length > 0 ? provided.slice(0, 3) : [];

  if (articles.length === 0) {
    try {
      const res = await getArticles('blog,news', 1, 3);
      articles = res.data;
    } catch {}
  }

  if (articles.length === 0) return null;

  return (
    <section className="mt-12 pt-10 border-t border-gray-200">
      <div className="flex items-end justify-between mb-6">
        <div>
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">READING</div>
          <h2 className="text-xl sm:text-2xl font-black text-gray-900 mt-1">相關閱讀</h2>
        </div>
        <Link href="/articles" className="text-sm font-black text-[#9F6B3E] hover:underline whitespace-nowrap">
          看全部 →
        </Link>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {articles.map((a) => (
          <Link
            key={a.id}
            href={`/articles/${a.slug}`}
            className="group block bg-white border border-[#e7d9cb] rounded-2xl overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all"
          >
            <div className="aspect-[16/10] bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] relative overflow-hidden">
              {a.featured_image ? (
                <ImageWithFallback
                  src={imageUrl(a.featured_image)!}
                  alt={a.title}
                  fill
                  sizes="(max-width: 640px) 100vw, 33vw"
                  className="object-cover transition-transform duration-500 group-hover:scale-105"
                />
              ) : (
                <LogoPlaceholder />
              )}
            </div>
            <div className="p-4">
              <div className="text-sm font-black text-gray-900 line-clamp-2 leading-snug min-h-[2.4em] group-hover:text-[#9F6B3E] transition-colors">
                {a.title}
              </div>
              {a.excerpt && (
                <p className="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed">
                  {a.excerpt}
                </p>
              )}
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
