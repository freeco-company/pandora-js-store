import type { Metadata } from 'next';
import Link from 'next/link';
import DodoNarrator from '@/components/DodoNarrator';

export const metadata: Metadata = {
  title: '找不到此頁面',
  robots: { index: false, follow: false },
};

export default function NotFound() {
  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
      <img
        src="/svg/empty/error_404.svg"
        alt=""
        width={224}
        height={224}
        aria-hidden
        className="mx-auto mb-2"
      />
      <h1 className="text-5xl font-bold text-[#9F6B3E]/30 mb-2">404</h1>
      <h2 className="text-xl font-semibold text-gray-900 mb-6">找不到此頁面</h2>
      <div className="max-w-md mx-auto mb-8 text-left">
        <DodoNarrator
          line="這裡好像沒有妳要找的東西。讓我帶妳回首頁吧。"
          mood="neutral"
          size={56}
        />
      </div>
      <div className="flex flex-wrap gap-3 justify-center mb-12">
        <Link
          href="/"
          className="inline-flex items-center px-6 py-3 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
        >
          回首頁
        </Link>
        <Link
          href="/products"
          className="inline-flex items-center px-6 py-3 bg-white border border-[#e7d9cb] text-[#9F6B3E] font-semibold rounded-full hover:border-[#9F6B3E] transition-colors"
        >
          瀏覽商品
        </Link>
        <Link
          href="/articles"
          className="inline-flex items-center px-6 py-3 bg-white border border-[#e7d9cb] text-[#9F6B3E] font-semibold rounded-full hover:border-[#9F6B3E] transition-colors"
        >
          閱讀文章
        </Link>
      </div>

      {/* Category shortcuts */}
      <div className="border-t border-gray-200 pt-8">
        <p className="text-sm text-gray-400 mb-4">或直接瀏覽分類</p>
        <div className="flex flex-wrap gap-2 justify-center">
          {[
            { label: '健康活力系列', slug: 'healthy-vitality-series', emoji: '🌿' },
            { label: '健康維持系列', slug: 'functional-health-series', emoji: '🍃' },
            { label: '美容美體系列', slug: 'body-beauty-series', emoji: '🌸' },
            { label: '體重管理', slug: 'slimming', emoji: '💪' },
          ].map((cat) => (
            <Link
              key={cat.slug}
              href={`/products?category=${cat.slug}`}
              className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#fdf7ef] border border-[#e7d9cb] rounded-full text-sm font-medium text-[#7a5836] hover:bg-[#f7eee3] transition-colors"
            >
              <span>{cat.emoji}</span>
              {cat.label}
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
