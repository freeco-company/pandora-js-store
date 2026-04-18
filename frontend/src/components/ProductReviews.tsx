'use client';

import { useState } from 'react';
import type { ProductReviewsData } from '@/lib/api';

function StarRating({ rating, size = 'sm' }: { rating: number; size?: 'sm' | 'lg' }) {
  const sizeClass = size === 'lg' ? 'text-xl' : 'text-sm';
  return (
    <span className={`${sizeClass} leading-none`} aria-label={`${rating} 顆星`}>
      {[1, 2, 3, 4, 5].map((i) => (
        <span key={i} className={i <= rating ? 'text-amber-400' : 'text-gray-200'}>
          ★
        </span>
      ))}
    </span>
  );
}

function DistributionBar({ star, count, total }: { star: number; count: number; total: number }) {
  const pct = total > 0 ? (count / total) * 100 : 0;
  return (
    <div className="flex items-center gap-2 text-xs">
      <span className="w-8 text-right text-gray-500">{star} 星</span>
      <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
        <div
          className="h-full bg-amber-400 rounded-full transition-all"
          style={{ width: `${pct}%` }}
        />
      </div>
      <span className="w-6 text-gray-400 text-right">{count}</span>
    </div>
  );
}

function timeAgo(dateStr: string): string {
  const now = Date.now();
  const then = new Date(dateStr).getTime();
  const diff = now - then;
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  if (days < 1) return '今天';
  if (days < 7) return `${days} 天前`;
  if (days < 30) return `${Math.floor(days / 7)} 週前`;
  if (days < 365) return `${Math.floor(days / 30)} 個月前`;
  return `${Math.floor(days / 365)} 年前`;
}

export default function ProductReviews({ data }: { data: ProductReviewsData }) {
  const [showAll, setShowAll] = useState(false);

  if (data.total_count === 0) return null;

  const visibleReviews = showAll ? data.reviews : data.reviews.slice(0, 5);
  const hasMore = data.reviews.length > 5;

  return (
    <section className="mt-12 pt-10 border-t border-gray-200">
      <div className="max-w-3xl mx-auto">
        <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">REVIEWS · 顧客評價</div>
        <h2 className="text-xl sm:text-2xl font-black text-gray-900 mt-1 mb-6">買家好評</h2>

        {/* Summary */}
        <div className="flex flex-col sm:flex-row gap-6 mb-8 p-5 bg-[#fdf7ef] rounded-2xl border border-[#e7d9cb]">
          {/* Average score */}
          <div className="flex flex-col items-center justify-center sm:min-w-[120px]">
            <div className="text-4xl font-black text-[#9F6B3E]">{data.average_rating}</div>
            <StarRating rating={Math.round(data.average_rating)} size="lg" />
            <div className="text-xs text-gray-500 mt-1">{data.total_count} 則評價</div>
          </div>

          {/* Distribution bars */}
          <div className="flex-1 space-y-1.5">
            {[5, 4, 3, 2, 1].map((star) => (
              <DistributionBar
                key={star}
                star={star}
                count={data.distribution[star] || 0}
                total={data.total_count}
              />
            ))}
          </div>
        </div>

        {/* Review list */}
        <div className="space-y-4">
          {visibleReviews.map((review) => (
            <div key={review.id} className="py-4 border-b border-gray-100 last:border-0">
              <div className="flex items-center justify-between mb-1.5">
                <div className="flex items-center gap-2">
                  <span className="text-sm font-medium text-gray-800">{review.reviewer_name}</span>
                  {review.is_verified_purchase && (
                    <span className="text-[10px] text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full font-medium">
                      已購買
                    </span>
                  )}
                </div>
                <span className="text-xs text-gray-400">{timeAgo(review.created_at)}</span>
              </div>
              <StarRating rating={review.rating} />
              {review.content && (
                <p className="mt-1.5 text-sm text-gray-600 leading-relaxed">{review.content}</p>
              )}
            </div>
          ))}
        </div>

        {/* Show more */}
        {hasMore && !showAll && (
          <button
            onClick={() => setShowAll(true)}
            className="mt-4 w-full py-2.5 text-sm text-[#9F6B3E] font-medium border border-[#e7d9cb] rounded-xl hover:bg-[#fdf7ef] transition-colors"
          >
            查看全部 {data.total_count} 則評價
          </button>
        )}
      </div>
    </section>
  );
}
