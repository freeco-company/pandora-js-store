'use client';

import { useState } from 'react';
import { imageUrl } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from './ImageWithFallback';

export default function ProductGallery({
  mainImage,
  gallery,
  productName,
}: {
  mainImage: string | null;
  gallery: string[] | null;
  productName: string;
}) {
  // Build all images: main image first, then gallery
  const allImages: string[] = [];
  if (mainImage) {
    const url = imageUrl(mainImage);
    if (url) allImages.push(url);
  }
  if (gallery) {
    for (const img of gallery) {
      const url = imageUrl(img);
      if (url && !allImages.includes(url)) allImages.push(url);
    }
  }

  const [selected, setSelected] = useState(0);

  if (allImages.length === 0) {
    return (
      <div className="relative aspect-square bg-gray-50 rounded-[10px] overflow-hidden">
        <LogoPlaceholder />
      </div>
    );
  }

  return (
    <div>
      {/* Main Image */}
      <div className="relative aspect-square bg-gray-50 rounded-[10px] overflow-hidden">
        <ImageWithFallback
          src={allImages[selected]}
          alt={productName}
          fill
          sizes="(max-width: 1024px) 100vw, 50vw"
          className="object-cover"
          priority
        />
      </div>

      {/* Thumbnails */}
      {allImages.length > 1 && (
        <div className="grid grid-cols-4 gap-3 mt-4">
          {allImages.map((img, i) => (
            <button
              key={i}
              onClick={() => setSelected(i)}
              className={`relative aspect-square bg-gray-50 rounded-lg overflow-hidden border-2 transition-colors ${
                i === selected ? 'border-[#9F6B3E]' : 'border-transparent'
              }`}
            >
              <ImageWithFallback
                src={img}
                alt={`${productName} - ${i + 1}`}
                fill
                sizes="120px"
                className="object-cover"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
