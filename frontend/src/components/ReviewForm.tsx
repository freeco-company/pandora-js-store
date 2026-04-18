'use client';

import { useState, useEffect } from 'react';
import { useAuth } from './AuthProvider';
import { getReviewableProducts, submitReview } from '@/lib/api';
import type { ReviewableItem } from '@/lib/api';
import { useToast } from './Toast';

function StarInput({ value, onChange }: { value: number; onChange: (v: number) => void }) {
  const [hover, setHover] = useState(0);
  return (
    <div className="flex gap-1">
      {[1, 2, 3, 4, 5].map((i) => (
        <button
          key={i}
          type="button"
          onMouseEnter={() => setHover(i)}
          onMouseLeave={() => setHover(0)}
          onClick={() => onChange(i)}
          className="text-2xl transition-colors focus:outline-none"
          aria-label={`${i} 星`}
        >
          <span className={(hover || value) >= i ? 'text-amber-400' : 'text-gray-200'}>★</span>
        </button>
      ))}
    </div>
  );
}

export default function ReviewForm({
  productId,
  productSlug,
  onReviewSubmitted,
}: {
  productId: number;
  productSlug: string;
  onReviewSubmitted?: () => void;
}) {
  const { token, isLoggedIn } = useAuth();
  const { toast } = useToast();
  const [reviewable, setReviewable] = useState<ReviewableItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [rating, setRating] = useState(5);
  const [content, setContent] = useState('');
  const [submitted, setSubmitted] = useState(false);

  useEffect(() => {
    if (!isLoggedIn || !token) {
      setLoading(false);
      return;
    }
    getReviewableProducts(token)
      .then((items) => {
        setReviewable(items.filter((r) => r.product_id === productId));
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [isLoggedIn, token, productId]);

  if (loading || !isLoggedIn || reviewable.length === 0 || submitted) return null;

  const item = reviewable[0]; // take the first reviewable order for this product

  const handleSubmit = async () => {
    if (!token || submitting) return;
    setSubmitting(true);
    try {
      await submitReview(token, {
        product_id: productId,
        order_id: item.order_id,
        rating,
        content: content.trim() || undefined,
      });
      toast('感謝您的評論！');
      setSubmitted(true);
      onReviewSubmitted?.();
    } catch {
      toast('送出失敗，請稍後再試');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="mt-6 p-5 bg-white border border-[#e7d9cb] rounded-2xl">
      <div className="text-sm font-bold text-gray-800 mb-3">撰寫您的評價</div>
      <div className="text-xs text-gray-500 mb-3">訂單 #{item.order_number}</div>

      <div className="mb-3">
        <div className="text-xs text-gray-500 mb-1">評分</div>
        <StarInput value={rating} onChange={setRating} />
      </div>

      <div className="mb-4">
        <textarea
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder="分享您的使用心得（選填）"
          maxLength={500}
          rows={3}
          className="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 focus:outline-none focus:border-[#9F6B3E] resize-none"
        />
        <div className="text-right text-[10px] text-gray-300 mt-0.5">{content.length}/500</div>
      </div>

      <button
        onClick={handleSubmit}
        disabled={submitting || rating === 0}
        className="w-full py-2.5 bg-[#9F6B3E] text-white text-sm font-medium rounded-xl hover:bg-[#8a5d35] disabled:opacity-50 transition-colors"
      >
        {submitting ? '送出中...' : '送出評價'}
      </button>
    </div>
  );
}
