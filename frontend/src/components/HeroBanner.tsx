'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { type Banner, type Product, imageUrl } from '@/lib/api';
import ImageWithFallback from './ImageWithFallback';

interface BannerSlide {
  id: string;
  desktopImage: string;
  mobileImage: string;
  link: string;
  title: string;
  subtitle?: string;
}

export default function HeroBanner({
  banners,
  products,
}: {
  banners: Banner[];
  products: Product[];
}) {
  // Build slides: API banners first, then fallback to product images
  const slides: BannerSlide[] = [];

  for (const b of banners) {
    const img = imageUrl(b.image);
    if (img) {
      slides.push({
        id: `banner-${b.id}`,
        desktopImage: img,
        mobileImage: imageUrl(b.mobile_image) || img,
        link: b.link || '/',
        title: b.title,
      });
    }
  }

  // Only use products as fallback if no banners
  if (slides.length === 0) {
    for (const p of products.filter((p) => p.image).slice(0, 5)) {
      const img = imageUrl(p.image)!;
      slides.push({
        id: `product-${p.id}`,
        desktopImage: img,
        mobileImage: img,
        link: `/products/${p.slug}`,
        title: p.name,
        subtitle: p.short_description,
      });
    }
  }

  const [current, setCurrent] = useState(0);

  const next = useCallback(() => {
    setCurrent((c) => (c + 1) % slides.length);
  }, [slides.length]);

  const prev = useCallback(() => {
    setCurrent((c) => (c - 1 + slides.length) % slides.length);
  }, [slides.length]);

  useEffect(() => {
    if (slides.length <= 1) return;
    const timer = setInterval(next, 5000);
    return () => clearInterval(timer);
  }, [next, slides.length]);

  if (slides.length === 0) return null;

  return (
    <section className="relative w-full bg-gray-100 overflow-hidden">
      {/* Desktop: fixed aspect ratio; Mobile: taller aspect ratio */}
      <div className="relative w-full h-[120vw] max-h-[600px] sm:h-[50vw] sm:max-h-[480px] sm:min-h-[280px]">
        {slides.map((slide, i) => (
          <Link
            key={slide.id}
            href={slide.link}
            className={`absolute inset-0 transition-opacity duration-700 ${
              i === current ? 'opacity-100 z-10' : 'opacity-0 z-0'
            }`}
          >
            {/* Desktop image */}
            <div
              key={i === current ? `kb-desktop-${current}` : undefined}
              className="absolute inset-0 hidden sm:block"
              style={i === current ? { animation: 'ken-burns 5s ease-out forwards' } : undefined}
            >
              <ImageWithFallback
                src={slide.desktopImage}
                alt={slide.title}
                fill
                sizes="100vw"
                className="object-cover"
                priority={i === 0}
                fetchPriority={i === 0 ? 'high' : 'auto'}
              />
            </div>
            {/* Mobile image */}
            <div
              key={i === current ? `kb-mobile-${current}` : undefined}
              className="absolute inset-0 sm:hidden"
              style={i === current ? { animation: 'ken-burns 5s ease-out forwards' } : undefined}
            >
              <ImageWithFallback
                src={slide.mobileImage}
                alt={slide.title}
                fill
                sizes="100vw"
                className="object-cover"
                priority={i === 0}
                fetchPriority={i === 0 ? 'high' : 'auto'}
              />
            </div>
            {/* Overlay */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/50 via-black/10 to-transparent" />
            <div className="absolute bottom-6 left-6 sm:bottom-10 sm:left-10 z-10">
              <h2 className="text-white text-lg sm:text-2xl lg:text-3xl font-bold drop-shadow-lg">
                {slide.title}
              </h2>
              {slide.subtitle && (
                <p className="text-white/80 text-sm sm:text-base mt-1 max-w-md line-clamp-1 drop-shadow">
                  {slide.subtitle}
                </p>
              )}
            </div>
          </Link>
        ))}

        {/* Navigation arrows */}
        {slides.length > 1 && (
          <>
            <button
              onClick={(e) => { e.preventDefault(); prev(); }}
              className="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/50 transition-colors"
              aria-label="上一張"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" className="w-5 h-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
              </svg>
            </button>
            <button
              onClick={(e) => { e.preventDefault(); next(); }}
              className="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-10 h-10 rounded-full bg-white/30 backdrop-blur-sm flex items-center justify-center text-white hover:bg-white/50 transition-colors"
              aria-label="下一張"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" className="w-5 h-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </button>

            {/* Dots */}
            <div className="absolute bottom-2 left-1/2 -translate-x-1/2 z-20 flex gap-1">
              {slides.map((_, i) => (
                <button
                  key={i}
                  onClick={(e) => { e.preventDefault(); setCurrent(i); }}
                  className="w-11 h-11 flex items-center justify-center group"
                  aria-label={`第 ${i + 1} 張`}
                >
                  <span
                    className={`block rounded-full transition-all ${
                      i === current ? 'bg-white w-6 h-2' : 'bg-white/60 group-hover:bg-white/90 w-2 h-2'
                    }`}
                  />
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </section>
  );
}
