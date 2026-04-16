'use client';

/**
 * Mobile bottom dock — 5 tabs with active pill, scale, and slide indicator.
 * Desktop (md+) hides this.
 */

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useCart } from './CartProvider';
import { useAuth } from './AuthProvider';
import { useMobileMenu } from './MobileMenuContext';

interface Tab {
  key: string;
  href?: string;
  onClick?: () => void;
  label: string;
  match: (path: string) => boolean;
  icon: (a: boolean) => React.ReactNode;
  badge?: number;
}

export default function BottomNav() {
  const pathname = usePathname() || '/';
  const { itemCount } = useCart();
  const { isLoggedIn } = useAuth();
  const { setOpen } = useMobileMenu();

  // Hide on product detail + cart + checkout + order-complete — those pages own the bottom bar
  if (pathname.startsWith('/products/') && pathname.split('/').length > 2) return null;
  if (pathname === '/cart' || pathname.startsWith('/cart/')) return null;
  if (pathname === '/checkout' || pathname.startsWith('/checkout/')) return null;
  if (pathname.startsWith('/order-complete')) return null;

  const icon = (d: string) => (a: boolean) => (
    <svg
      className={`transition-all duration-300 ${a ? 'w-7 h-7' : 'w-6 h-6'}`}
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      strokeWidth={a ? 2.4 : 1.9}
    >
      <path strokeLinecap="round" strokeLinejoin="round" d={d} />
    </svg>
  );

  const tabs: Tab[] = [
    {
      key: 'home',
      href: '/',
      label: '首頁',
      match: (p) => p === '/',
      icon: icon('M3 12l9-9 9 9v9a1.5 1.5 0 01-1.5 1.5h-3.75V15a1.5 1.5 0 00-1.5-1.5h-3a1.5 1.5 0 00-1.5 1.5v7.5H4.5A1.5 1.5 0 013 21z'),
    },
    {
      key: 'products',
      href: '/products',
      label: '商品',
      match: (p) => p.startsWith('/products') || p.startsWith('/articles'),
      icon: icon('M3.75 7.5V19.5a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5V7.5M3.75 7.5h16.5M8.25 7.5V5.25A2.25 2.25 0 0110.5 3h3a2.25 2.25 0 012.25 2.25V7.5'),
    },
    {
      key: 'cart',
      href: '/cart',
      label: '購物車',
      match: (p) => p.startsWith('/cart') || p.startsWith('/checkout'),
      icon: icon('M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'),
      badge: itemCount,
    },
    {
      key: 'account',
      href: '/account',
      label: isLoggedIn ? '我的' : '登入',
      match: (p) => p.startsWith('/account') || p.startsWith('/login'),
      icon: icon('M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'),
    },
    {
      key: 'menu',
      onClick: () => setOpen(true),
      label: '選單',
      match: () => false,
      icon: icon('M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5'),
    },
  ];

  const activeIndex = tabs.findIndex((t) => t.match(pathname));

  return (
    <nav
      className="md:hidden fixed bottom-0 left-0 right-0 z-[60] bg-white/95 backdrop-blur-xl border-t border-[#e7d9cb] shadow-[0_-8px_30px_rgba(159,107,62,0.08)]"
      style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
      aria-label="底部導覽"
    >
      {/* Sliding top indicator */}
      {activeIndex >= 0 && (
        <div
          className="absolute top-0 h-1 bg-gradient-to-r from-[#b08257] via-[#9F6B3E] to-[#85572F] rounded-b-full transition-all duration-500 ease-out"
          style={{
            width: `${100 / tabs.length}%`,
            left: `${(activeIndex * 100) / tabs.length}%`,
          }}
        />
      )}

      <div className="flex items-stretch justify-around h-20 relative">
        {tabs.map((tab) => {
          const active = tab.match(pathname);
          const body = (
            <>
              {/* Active pill behind icon */}
              <span
                className={`absolute w-14 h-14 rounded-2xl transition-all duration-300 ${
                  active
                    ? 'bg-gradient-to-br from-[#fdf7ef] to-[#f0dfc8] scale-100 opacity-100 shadow-[0_6px_14px_rgba(159,107,62,0.25)]'
                    : 'scale-50 opacity-0'
                }`}
                style={{ top: '8px' }}
              />
              {/* Icon + badge */}
              <span className={`relative z-10 flex items-center justify-center transition-all duration-300 ${active ? 'text-[#9F6B3E] -translate-y-[2px]' : 'text-slate-500'}`}>
                {tab.icon(active)}
                {tab.badge !== undefined && tab.badge > 0 && (
                  <span
                    className="absolute -top-1 -right-2 min-w-[18px] h-[18px] px-1 rounded-full bg-[#9F6B3E] text-white text-[10px] font-black flex items-center justify-center leading-none ring-2 ring-white"
                  >
                    {tab.badge > 99 ? '99+' : tab.badge}
                  </span>
                )}
              </span>
              {/* Label */}
              <span
                className={`relative z-10 text-[11px] mt-1 transition-all duration-300 ${
                  active ? 'font-black text-[#9F6B3E]' : 'font-bold text-slate-500'
                }`}
              >
                {tab.label}
              </span>
            </>
          );

          const sharedClass =
            'flex-1 flex flex-col items-center justify-center touch-target relative select-none active:scale-95 transition-transform';

          if (tab.href) {
            return (
              <Link key={tab.key} href={tab.href} className={sharedClass} aria-current={active ? 'page' : undefined}>
                {body}
              </Link>
            );
          }
          return (
            <button
              key={tab.key}
              type="button"
              className={sharedClass}
              onClick={tab.onClick}
              aria-label={tab.label}
            >
              {body}
            </button>
          );
        })}
      </div>
    </nav>
  );
}
