'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { type Product, imageUrl } from '@/lib/api';
import { formatPrice } from '@/lib/format';
import { ProductBadges } from './HealthFoodBadge';
import { useCart } from './CartProvider';
import { flyToCart } from '@/lib/animations';
import { useToast } from './Toast';

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
              <Image
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
            <div className="w-full h-full flex items-center justify-center text-gray-300">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-16 h-16">
                <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
              </svg>
            </div>
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
            <div className="flex items-baseline gap-1 sm:gap-2">
              <span className="text-sm sm:text-lg font-bold text-[#9F6B3E]">
                {formatPrice(product.combo_price ?? product.price)}
              </span>
              <span className="text-xs sm:text-sm text-gray-400 line-through">
                {formatPrice(product.price)}
              </span>
            </div>
          ) : (
            <span className="text-sm sm:text-lg font-bold text-[#9F6B3E]">
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
