import type { Metadata } from 'next';
import Link from 'next/link';

export const metadata: Metadata = {
  title: '找不到此頁面',
  robots: { index: false, follow: false },
};

export default function NotFound() {
  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
      <h1 className="text-6xl font-bold text-gray-200 mb-4">404</h1>
      <h2 className="text-xl font-semibold text-gray-900 mb-2">找不到此頁面</h2>
      <p className="text-gray-500 mb-8">您要找的頁面不存在或已被移除。</p>
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
