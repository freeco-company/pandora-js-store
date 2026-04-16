/**
 * Shared fly-to-cart animation.
 * Desktop: flies to the header cart icon (top-right).
 * Mobile: flies to the BottomNav cart tab (bottom-center).
 */
export function flyToCart(fromElement: HTMLElement): void {
  const rect = fromElement.getBoundingClientRect();
  const startX = rect.left + rect.width / 2;
  const startY = rect.top + rect.height / 2;

  // Mobile breakpoint check — Tailwind's md: (≥768px) is desktop
  const isMobile = window.matchMedia('(max-width: 767px)').matches;

  let endX: number;
  let endY: number;

  if (isMobile) {
    // Target the BottomNav cart link (third tab) — it has aria-current or data-cart-icon
    // We search for the BottomNav's cart anchor
    const bottomNavCart =
      document.querySelector('nav[aria-label="底部導覽"] a[href="/cart"]') ||
      document.querySelector('a[href="/cart"][data-cart-icon]');
    if (bottomNavCart) {
      const r = bottomNavCart.getBoundingClientRect();
      endX = r.left + r.width / 2;
      endY = r.top + r.height / 2;
    } else {
      // Fallback: center-bottom of viewport
      endX = window.innerWidth / 2;
      endY = window.innerHeight - 40;
    }
  } else {
    // Desktop: header cart
    const headerCart =
      document.querySelector('header a[href="/cart"]') ||
      document.querySelector('a[href="/cart"][data-cart-icon]');
    if (headerCart) {
      const r = headerCart.getBoundingClientRect();
      endX = r.left + r.width / 2;
      endY = r.top + r.height / 2;
    } else {
      endX = window.innerWidth - 40;
      endY = 24;
    }
  }

  const dot = document.createElement('div');
  Object.assign(dot.style, {
    position: 'fixed',
    left: `${startX}px`,
    top: `${startY}px`,
    width: '14px',
    height: '14px',
    borderRadius: '50%',
    background: '#9F6B3E',
    boxShadow: '0 0 8px rgba(159, 107, 62, 0.5)',
    zIndex: '9999',
    pointerEvents: 'none',
    transition: 'all 0.6s cubic-bezier(0.2, 0.8, 0.2, 1)',
  });

  document.body.appendChild(dot);

  requestAnimationFrame(() => {
    Object.assign(dot.style, {
      left: `${endX}px`,
      top: `${endY}px`,
      width: '8px',
      height: '8px',
      opacity: '0.3',
    });
  });

  setTimeout(() => {
    window.dispatchEvent(new CustomEvent('cart-item-added'));
  }, 500);

  setTimeout(() => {
    dot.remove();
  }, 650);
}
