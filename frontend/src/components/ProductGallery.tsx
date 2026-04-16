'use client';

import { useState } from 'react';
import Image from 'next/image';
import { imageUrl } from '@/lib/api';

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
      <div className="relative aspect-square bg-gray-50 rounded-[10px] overflow-hidden flex items-center justify-center text-gray-300">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1} stroke="currentColor" className="w-24 h-24">
          <path strokeLinecap="round" strokeLinejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
        </svg>
      </div>
    );
  }

  return (
    <div>
      {/* Main Image */}
      <div className="relative aspect-square bg-gray-50 rounded-[10px] overflow-hidden">
        <Image
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
              <Image
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
