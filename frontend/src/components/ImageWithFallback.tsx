'use client';

import { useState } from 'react';
import Image, { type ImageProps } from 'next/image';

/**
 * Drop-in replacement for next/image that shows the brand logo
 * when the image fails to load (broken URL, 404, etc.).
 */
export default function ImageWithFallback(props: ImageProps) {
  const [error, setError] = useState(false);

  if (error) {
    return <LogoPlaceholder />;
  }

  return <Image {...props} onError={() => setError(true)} />;
}

/** Centered brand logo on warm gradient — used when no image is available. */
export function LogoPlaceholder() {
  return (
    <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3]">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/logo-placeholder.svg"
        alt=""
        className="w-2/5 h-2/5 object-contain opacity-50"
      />
    </div>
  );
}
