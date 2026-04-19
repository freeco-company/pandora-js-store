'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { type Product, imageUrl } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';
import { formatPrice } from '@/lib/format';
import { ProductBadges } from './HealthFoodBadge';
import { useCart } from './CartProvider';
import { flyToCart } from '@/lib/animations';
import { useToast } from './Toast';
import WishlistButton from './WishlistButton';

export default function ProductCard({ product }: { product: Product }) {
  const { addToCart } = useCart();
  const { toast } = useToast();
  const [added, setAdded] = useState(false);
  const cardRef = useRef<HTMLDivElement>(null);
  const hasDiscount = product.combo_price || product.vip_price;
  const isOutOfStock = product.stock_status === 'outofstock';

  // 3D tilt + spotlight follows cursor
  const handleMove = (e: React.MouseEvent) => {
    const el = cardRef.current;
    if (!el) return;
    // Skip 3D tilt on touch devices entirely — saves main-thread work on mobile
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    const rect = el.getBoundingClientRect();
    const px = (e.clientX - rect.left) / rect.width;
    const py = (e.clientY - rect.top) / rect.height;
    const rotY = (px - 0.5) * 10;
    const rotX = (0.5 - py) * 8;
    el.style.transform = `perspective(900px) rotateX(${rotX}deg) rotateY(${rotY}deg) translateY(-4px)`;
    el.style.setProperty('--spot-x', `${px * 100}%`);
    el.style.setProperty('--spot-y', `${py * 100}%`);
  };
  const handleLeave = () => {
    const el = cardRef.current;
    if (el) {
      el.style.transform = '';
      el.style.removeProperty('--spot-x');
      el.style.removeProperty('--spot-y');
    }
  };

  return (
    <div
      ref={cardRef}
      data-cursor="card"
      data-cursor-label="查看"
      className="group relative bg-white rounded-2xl overflow-hidden"
      style={{
        border: '1px solid rgba(0, 0, 0, 0.05)',
        boxShadow: '0px 12px 18px -6px rgba(34, 56, 101, 0.04)',
        transition: 'transform 0.35s cubic-bezier(0.2, 0.9, 0.3, 1.1), box-shadow 0.35s ease',
        transformStyle: 'preserve-3d',
      }}
      onMouseMove={handleMove}
      onMouseLeave={handleLeave}
      onMouseEnter={(e) => {
        (e.currentTarget as HTMLDivElement).style.boxShadow =
          '0 24px 48px -12px rgba(159, 107, 62, 0.28), 0 0 0 1px rgba(159, 107, 62, 0.1)';
      }}
      onMouseOut={(e) => {
        if (!(e.currentTarget as HTMLDivElement).contains(e.relatedTarget as Node)) {
          (e.currentTarget as HTMLDivElement).style.boxShadow =
            '0px 12px 18px -6px rgba(34, 56, 101, 0.04)';
        }
      }}
    >
      {/* Cursor-following spotlight overlay */}
      <span
        aria-hidden
        className="pointer-events-none absolute inset-0 z-[1] opacity-0 group-hover:opacity-100 transition-opacity duration-500"
        style={{
          background: 'radial-gradient(380px circle at var(--spot-x, 50%) var(--spot-y, 50%), rgba(255, 236, 207, 0.45), transparent 60%)',
          mixBlendMode: 'soft-light',
        }}
      />
      <Link href={`/products/${product.slug}`} className="block">
        <div className="relative aspect-square bg-gray-50 overflow-hidden">
          {product.image ? (
            <>
              <ImageWithFallback
                src={imageUrl(product.image)!}
                alt={product.name}
                fill
                sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
                className="object-cover transition-transform duration-[800ms] ease-[cubic-bezier(0.2,0.9,0.3,1)] group-hover:scale-[1.08]"
              />
              {/* Warm gradient shimmer on hover */}
              <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none bg-gradient-to-t from-[#9F6B3E]/20 via-transparent to-transparent" />
            </>
          ) : (
            <LogoPlaceholder />
          )}
          {isOutOfStock && (
            <div className="absolute inset-0 bg-black/40 flex items-center justify-center">
              <span className="bg-white/90 text-gray-700 text-sm font-bold px-3 py-1 rounded-full">
                售完
              </span>
            </div>
          )}
        </div>
      </Link>
      {/* Wishlist heart — sits over the image, above the card link, so taps don't navigate */}
      <div className="absolute top-2 right-2 z-[2]">
        <WishlistButton productId={product.id} variant="card" />
      </div>

      <div className="p-2.5 sm:p-3">
        <div className="mb-1">
          <ProductBadges badges={product.badges} hfCertNo={product.hf_cert_no} />
        </div>
        <Link href={`/products/${product.slug}`}>
          <h3 className="font-semibold text-gray-900 mb-1 sm:mb-2 text-xs sm:text-base line-clamp-2 hover:text-[#9F6B3E] transition-colors">
            {product.name}
          </h3>
        </Link>

        <div className="mb-2 sm:mb-3">
          {hasDiscount ? (
            <div>
              <div className="flex items-baseline gap-1.5 sm:gap-2">
                <span className="text-lg sm:text-2xl font-black text-[#c0392b] leading-none">
                  {formatPrice(product.vip_price ?? product.combo_price ?? product.price)}
                </span>
                <span className="text-[11px] sm:text-sm text-gray-400 line-through">
                  {formatPrice(product.price)}
                </span>
              </div>
              {(() => {
                const bestPrice = product.vip_price ?? product.combo_price;
                const saving = bestPrice ? product.price - bestPrice : 0;
                return saving > 0 ? (
                  <div className="mt-1 inline-flex items-center px-1.5 py-0.5 rounded-full bg-[#c0392b]/10">
                    <span className="text-[10px] font-black text-[#c0392b]">
                      省 {formatPrice(saving)}
                    </span>
                  </div>
                ) : null;
              })()}
            </div>
          ) : (
            <span className="text-lg sm:text-2xl font-black text-[#c0392b] leading-none">
              {formatPrice(product.price)}
            </span>
          )}
        </div>

        {isOutOfStock ? (
          <button
            disabled
            className="w-full min-h-[40px] py-2 px-3 sm:px-4 bg-gray-300 text-gray-500 text-sm font-semibold rounded-full cursor-not-allowed"
          >
            已售完
          </button>
        ) : (
          <button
            onClick={(e) => {
              addToCart(product);
              flyToCart(e.currentTarget as HTMLElement);
              toast('已加入購物車');
              if (!added) {
                setAdded(true);
                setTimeout(() => setAdded(false), 1500);
              }
            }}
            className="w-full min-h-[40px] py-2 px-3 sm:px-4 bg-[#9F6B3E] text-white text-sm font-semibold rounded-full hover:bg-[#85572F] active:scale-95 transition-all btn-press"
          >
            {added ? (
              <span style={{ display: 'inline-block', animation: 'checkmark-pop 0.3s ease' }}>✓</span>
            ) : (
              '加入購物車'
            )}
          </button>
        )}
      </div>
    </div>
  );
}
