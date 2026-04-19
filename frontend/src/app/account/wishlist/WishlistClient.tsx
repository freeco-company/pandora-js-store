'use client';

/**
 * Customer wishlist — client logic. Wrapped by page.tsx (server) which
 * owns the noindex metadata, since metadata exports must live in a
 * server component.
 */

import { useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { useWishlist } from '@/components/WishlistProvider';
import { useCart } from '@/components/CartProvider';
import { useToast } from '@/components/Toast';
import ImageWithFallback, { LogoPlaceholder } from '@/components/ImageWithFallback';
import LogoLoader from '@/components/LogoLoader';
import { imageUrl, type Product } from '@/lib/api';
import { formatPrice } from '@/lib/format';

export default function WishlistClient() {
  const router = useRouter();
  const { isLoggedIn, loading: authLoading } = useAuth();
  const { items, loading, remove } = useWishlist();
  const { addToCart } = useCart();
  const { toast } = useToast();

  useEffect(() => {
    if (!authLoading && !isLoggedIn) router.replace('/account');
  }, [isLoggedIn, authLoading, router]);

  if (authLoading || loading) {
    return (
      <div className="min-h-[60vh] flex items-center justify-center">
        <LogoLoader size={64} />
      </div>
    );
  }

  return (
    <div className="max-w-[1100px] mx-auto px-5 sm:px-6 lg:px-8 py-8 sm:py-12">
      <div className="flex items-center gap-3 mb-2">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="#c0392b" stroke="none" aria-hidden>
          <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
        </svg>
        <h1 className="text-2xl sm:text-3xl font-black text-gray-900">我的收藏</h1>
      </div>
      <p className="text-sm text-gray-500 mb-8">收藏喜歡的商品，下次找回更快</p>

      {items.length === 0 ? (
        <div className="text-center py-16 border-2 border-dashed border-[#e7d9cb] rounded-3xl bg-[#fdf7ef]">
          <div className="text-5xl mb-3 opacity-40">♡</div>
          <p className="text-gray-600 mb-4">還沒收藏任何商品</p>
          <Link
            href="/products"
            className="inline-flex items-center gap-2 px-6 py-3 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-colors"
          >
            去逛逛 →
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {items.map((item) => {
            const p = item.product;
            const isOos = p.stock_status === 'outofstock' || !p.is_active;
            const bestPrice = p.vip_price ?? p.combo_price ?? p.price;
            const saving = bestPrice < p.price ? p.price - bestPrice : 0;

            return (
              <div
                key={item.id}
                className="bg-white rounded-2xl border border-[#e7d9cb]/60 overflow-hidden hover:shadow-md hover:shadow-[#9F6B3E]/10 transition-shadow"
              >
                <div className="flex">
                  <Link
                    href={`/products/${p.slug}`}
                    className="relative w-28 sm:w-32 aspect-square shrink-0 bg-[#fdf7ef]"
                  >
                    {p.image ? (
                      <ImageWithFallback
                        src={imageUrl(p.image)!}
                        alt={p.name}
                        fill
                        sizes="128px"
                        className="object-cover"
                      />
                    ) : (
                      <LogoPlaceholder />
                    )}
                  </Link>
                  <div className="flex-1 min-w-0 p-3 flex flex-col">
                    <Link href={`/products/${p.slug}`}>
                      <h3 className="text-sm font-black text-gray-900 line-clamp-2 hover:text-[#9F6B3E]">
                        {p.name}
                      </h3>
                    </Link>
                    <div className="mt-1 flex items-baseline gap-1.5">
                      <span className="text-base font-black text-[#c0392b]">
                        {formatPrice(bestPrice)}
                      </span>
                      {saving > 0 && (
                        <span className="text-[10px] text-gray-400 line-through">
                          {formatPrice(p.price)}
                        </span>
                      )}
                    </div>
                    <div className="mt-auto flex items-center gap-1.5 pt-2">
                      <button
                        type="button"
                        onClick={() => {
                          if (isOos) return;
                          addToCart(p as unknown as Product);
                          toast(`已加入：${p.name}`);
                        }}
                        disabled={isOos}
                        className="flex-1 px-2 py-1.5 text-[11px] font-black rounded-full bg-[#9F6B3E] text-white hover:bg-[#85572F] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                      >
                        {isOos ? '售完' : '加入購物車'}
                      </button>
                      <button
                        type="button"
                        onClick={() => remove(p.id)}
                        className="px-2 py-1.5 text-[11px] font-black rounded-full text-gray-500 hover:text-[#c0392b] hover:bg-[#fdf7ef] transition-colors"
                        aria-label="移除收藏"
                      >
                        移除
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
