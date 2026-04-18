import type { Metadata } from 'next';
import Link from 'next/link';
import { getAggregateReviews, imageUrl } from '@/lib/api';
import { faqSchema, breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 3600;

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';

export const metadata: Metadata = {
  title: '婕樂纖評價｜真實買家使用心得與商品評論',
  description:
    '婕樂纖仙女館真實買家評價彙整。纖飄錠、爆纖錠、纖纖飲X、水光錠、益生菌等全系列商品使用心得。官方授權正品，安心選購。',
  alternates: { canonical: '/reviews' },
  openGraph: {
    title: '婕樂纖評價｜真實買家使用心得',
    description: '看看其他仙女們怎麼說！婕樂纖全系列商品真實評價與推薦。',
  },
};

function StarRating({ rating }: { rating: number }) {
  return (
    <span className="text-sm leading-none" aria-label={`${rating} 顆星`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <span key={i} className={i <= rating ? 'text-amber-400' : 'text-gray-200'}>★</span>
      ))}
    </span>
  );
}

function timeAgo(dateStr: string): string {
  const days = Math.floor((Date.now() - new Date(dateStr).getTime()) / (1000 * 60 * 60 * 24));
  if (days < 1) return '今天';
  if (days < 7) return `${days} 天前`;
  if (days < 30) return `${Math.floor(days / 7)} 週前`;
  if (days < 365) return `${Math.floor(days / 30)} 個月前`;
  return `${Math.floor(days / 365)} 年前`;
}

export default async function ReviewsPage() {
  let data = null;
  try {
    data = await getAggregateReviews();
  } catch {
    // degrade gracefully
  }

  const faqs = faqSchema([
    { question: '婕樂纖產品是正品嗎？', answer: '婕樂纖仙女館為 JEROSSE 官方正品授權經銷商，所有產品均為原廠正品，品質保證。' },
    { question: '婕樂纖纖飄錠效果如何？', answer: '纖飄錠榮獲國家健康食品認證，經動物實驗證實有助於不易形成體脂肪。眾多買家好評推薦。' },
    { question: '買越多真的越便宜嗎？', answer: '是的！任選 2 件即享 1+1 搭配價，組合滿 NT$4,000 全車自動升級 VIP 最低優惠價。' },
    { question: '可以在哪裡購買婕樂纖？', answer: '您可以在婕樂纖仙女館官網 pandora.js-store.com.tw 購買官方正品，享受三階梯優惠價格。' },
  ]);

  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '婕樂纖評價' },
  ]);

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(faqs, breadcrumbs) }}
      />

      <div className="max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-16">
        {/* Hero */}
        <div className="text-center mb-12">
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-2">REVIEWS · 顧客好評</div>
          <h1 className="text-3xl sm:text-4xl font-black text-gray-900 mb-3">婕樂纖評價</h1>
          <p className="text-gray-600 max-w-xl mx-auto">
            來自真實買家的使用心得。婕樂纖仙女館是 JEROSSE 官方正品授權經銷商，安心選購。
          </p>

          {data && (
            <div className="mt-6 inline-flex items-center gap-3 bg-[#fdf7ef] border border-[#e7d9cb] rounded-2xl px-6 py-3">
              <span className="text-3xl font-black text-[#9F6B3E]">{data.average_rating}</span>
              <div className="text-left">
                <StarRating rating={Math.round(data.average_rating)} />
                <div className="text-xs text-gray-500">{data.total_count} 則評價</div>
              </div>
            </div>
          )}
        </div>

        {/* Per-product review summary */}
        {data && data.products.length > 0 && (
          <section className="mb-12">
            <h2 className="text-lg font-bold text-gray-900 mb-4">各商品評價</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {data.products.map((p) => (
                <Link
                  key={p.product_id}
                  href={`/products/${p.product_slug}`}
                  className="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-[#e7d9cb] hover:bg-[#fdf7ef] transition-colors"
                >
                  {p.product_image && (
                    <img
                      src={imageUrl(p.product_image) || ''}
                      alt={p.product_name}
                      className="w-12 h-12 rounded-lg object-cover bg-gray-100"
                      loading="lazy"
                    />
                  )}
                  <div className="flex-1 min-w-0">
                    <div className="text-sm font-medium text-gray-800 truncate">{p.product_name}</div>
                    <div className="flex items-center gap-2 mt-0.5">
                      <StarRating rating={Math.round(p.average_rating)} />
                      <span className="text-xs text-gray-400">{p.count} 則</span>
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          </section>
        )}

        {/* Recent reviews */}
        {data && data.recent_reviews.length > 0 && (
          <section className="mb-12">
            <h2 className="text-lg font-bold text-gray-900 mb-4">最新評價</h2>
            <div className="space-y-4">
              {data.recent_reviews.map((r) => (
                <div key={r.id} className="py-4 border-b border-gray-100 last:border-0">
                  <div className="flex items-center justify-between mb-1">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-gray-800">{r.reviewer_name}</span>
                      {r.is_verified_purchase && (
                        <span className="text-[10px] text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full font-medium">已購買</span>
                      )}
                    </div>
                    <span className="text-xs text-gray-400">{timeAgo(r.created_at)}</span>
                  </div>
                  <StarRating rating={r.rating} />
                  {r.content && <p className="mt-1.5 text-sm text-gray-600">{r.content}</p>}
                  <Link
                    href={`/products/${r.product_slug}`}
                    className="mt-1 inline-block text-xs text-[#9F6B3E] hover:underline"
                  >
                    {r.product_name}
                  </Link>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* FAQ section — visible + schema.org */}
        <section>
          <h2 className="text-lg font-bold text-gray-900 mb-4">常見問題</h2>
          <div className="space-y-3">
            {[
              { q: '婕樂纖產品是正品嗎？', a: '婕樂纖仙女館為 JEROSSE 官方正品授權經銷商，所有產品均為原廠正品，品質保證。' },
              { q: '婕樂纖纖飄錠效果如何？', a: '纖飄錠榮獲國家健康食品認證，經動物實驗證實有助於不易形成體脂肪。眾多買家好評推薦。' },
              { q: '買越多真的越便宜嗎？', a: '是的！任選 2 件即享 1+1 搭配價，組合滿 NT$4,000 全車自動升級 VIP 最低優惠價。' },
              { q: '可以在哪裡購買婕樂纖？', a: '您可以在婕樂纖仙女館官網 pandora.js-store.com.tw 購買官方正品，享受三階梯優惠價格。' },
            ].map(({ q, a }) => (
              <details key={q} className="group border border-gray-100 rounded-xl p-4">
                <summary className="text-sm font-medium text-gray-800 cursor-pointer list-none flex justify-between items-center">
                  {q}
                  <span className="text-gray-400 group-open:rotate-180 transition-transform">▾</span>
                </summary>
                <p className="mt-2 text-sm text-gray-600 leading-relaxed">{a}</p>
              </details>
            ))}
          </div>
        </section>

        {/* CTA */}
        <div className="mt-12 text-center">
          <Link
            href="/products"
            className="inline-block px-8 py-3 bg-[#9F6B3E] text-white font-medium rounded-full hover:bg-[#8a5d35] transition-colors"
          >
            開始選購
          </Link>
        </div>
      </div>
    </>
  );
}
