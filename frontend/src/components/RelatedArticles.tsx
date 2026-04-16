import Link from 'next/link';
import Image from 'next/image';
import { getArticles, imageUrl } from '@/lib/api';

/**
 * Server-rendered "相關閱讀" strip.
 * Pulls the 3 most recent 婕樂纖誌 (blog+news) articles.
 * Shown on the product detail page as an internal-linking SEO boost.
 */
export default async function RelatedArticles() {
  let articles: Awaited<ReturnType<typeof getArticles>>['data'] = [];
  try {
    const res = await getArticles('blog,news', 1, 3);
    articles = res.data;
  } catch {}

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
                <Image
                  src={imageUrl(a.featured_image)!}
                  alt={a.title}
                  fill
                  sizes="(max-width: 640px) 100vw, 33vw"
                  className="object-cover transition-transform duration-500 group-hover:scale-105"
                />
              ) : (
                <div className="w-full h-full flex items-center justify-center text-3xl text-[#9F6B3E]/40">📖</div>
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
