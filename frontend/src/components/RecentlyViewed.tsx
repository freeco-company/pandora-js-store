'use client';

import Link from 'next/link';
import { useRecentlyViewed } from '@/hooks/useRecentlyViewed';
import { imageUrl } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import { formatPrice } from '@/lib/format';

/**
 * Horizontal scrollable strip of recently viewed products.
 * Pass `excludeSlug` on a product-detail page to hide the current product.
 * Renders nothing if the list is empty (safe to include anywhere).
 */
export default function RecentlyViewed({
  excludeSlug,
  title = '最近看過',
}: {
  excludeSlug?: string;
  title?: string;
}) {
  const items = useRecentlyViewed(excludeSlug);
  if (items.length === 0) return null;

  return (
    <section className="mt-10">
      <div className="flex items-center justify-between mb-4">
        <div>
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">HISTORY</div>
          <h2 className="text-lg sm:text-xl font-black text-gray-900 mt-0.5">{title}</h2>
        </div>
      </div>
      <div className="flex gap-3 overflow-x-auto scrollbar-hide snap-x snap-mandatory -mx-4 sm:mx-0 px-4 sm:px-0 pb-1">
        {items.map((p) => (
          <Link
            key={p.slug}
            href={`/products/${p.slug}`}
            className="shrink-0 snap-start w-32 sm:w-36 rounded-2xl bg-white border border-[#e7d9cb] overflow-hidden hover:shadow-md hover:-translate-y-0.5 transition-all"
          >
            <div className="aspect-square bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] relative">
              {p.image ? (
                <ImageWithFallback
                  src={imageUrl(p.image)!}
                  alt={p.name}
                  fill
                  sizes="144px"
                  className="object-cover"
                />
              ) : (
                <LogoPlaceholder />
              )}
            </div>
            <div className="p-2">
              <div className="text-[11px] font-black text-gray-900 line-clamp-2 leading-snug min-h-[2.4em]">
                {p.name}
              </div>
              <div className="text-xs font-black text-[#9F6B3E] mt-1">
                {formatPrice(p.price)}
              </div>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}
