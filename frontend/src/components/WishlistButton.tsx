'use client';

/**
 * Heart toggle. Used inside ProductCard (top-right overlay) and on the
 * product detail page next to the title.
 *
 * Renders nothing fancy — just an accessible button that flips between
 * outlined and filled hearts. The provider handles guest/auth split,
 * so this component is purely presentational.
 *
 * Variant `card` is small + absolute-positionable; `inline` is larger
 * for use inside text flow.
 */

import { useWishlist } from './WishlistProvider';

type Variant = 'card' | 'inline';

export default function WishlistButton({
  productId,
  variant = 'card',
  className = '',
}: {
  productId: number;
  variant?: Variant;
  className?: string;
}) {
  const { has, toggle } = useWishlist();
  const inList = has(productId);

  const handleClick = (e: React.MouseEvent) => {
    // Card variant sits inside <Link> grids — don't navigate when toggling.
    e.preventDefault();
    e.stopPropagation();
    toggle(productId);
  };

  const sizes = variant === 'card'
    ? 'w-9 h-9'
    : 'w-11 h-11';

  return (
    <button
      type="button"
      onClick={handleClick}
      aria-pressed={inList}
      aria-label={inList ? '從收藏移除' : '加入收藏'}
      className={`${sizes} rounded-full flex items-center justify-center transition-all touch-target ${
        inList
          ? 'bg-[#c0392b] text-white shadow-md shadow-[#c0392b]/30 hover:bg-[#a52e22]'
          : 'bg-white/90 backdrop-blur text-gray-500 hover:text-[#c0392b] hover:bg-white border border-gray-200'
      } ${className}`}
    >
      <svg
        width={variant === 'card' ? 18 : 22}
        height={variant === 'card' ? 18 : 22}
        viewBox="0 0 24 24"
        fill={inList ? 'currentColor' : 'none'}
        stroke="currentColor"
        strokeWidth={inList ? 0 : 2}
        strokeLinecap="round"
        strokeLinejoin="round"
        aria-hidden
      >
        <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z" />
      </svg>
    </button>
  );
}
