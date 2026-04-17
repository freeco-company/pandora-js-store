'use client';

/**
 * Mobile menu drawer — slides in from the right.
 * Rendered at top-level of layout (not inside Header) so it escapes any
 * ancestor with backdrop-filter / transform that would create a fixed-pos
 * containing block.
 */

import Link from 'next/link';
import Icons from '@/components/SvgIcons';
import { useCart } from './CartProvider';
import { useAuth } from './AuthProvider';
import { useMobileMenu } from './MobileMenuContext';
import Logo from './Logo';
import SiteIcon from '@/components/SiteIcon';
import SearchModal from './SearchModal';
import { useState } from 'react';

const NAV_LINKS = [
  { href: '/', label: '首頁', icon: <Icons.Heart className="w-5 h-5 text-[#9F6B3E]" /> },
  { href: '/products', label: '全館商品', icon: <Icons.ShoppingBag className="w-5 h-5 text-[#9F6B3E]" /> },
  { href: '/articles', label: '專欄文章', icon: <Icons.Leaf className="w-5 h-5 text-[#9F6B3E]" /> },
  { href: '/about', label: '關於 FP', icon: <Icons.Ribbon className="w-5 h-5 text-[#9F6B3E]" /> },
  { href: '/join', label: '加入我們', icon: <Icons.Diamond className="w-5 h-5 text-[#9F6B3E]" /> },
];

export default function MobileDrawer() {
  const { open, setOpen } = useMobileMenu();
  const { itemCount } = useCart();
  const { isLoggedIn, customer, logout } = useAuth();
  const [searchOpen, setSearchOpen] = useState(false);

  const close = () => setOpen(false);

  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 z-[90] md:hidden bg-black/40 backdrop-blur-sm transition-opacity duration-300 ${
          open ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
        onClick={close}
        aria-hidden
      />

      {/* Drawer */}
      <aside
        className={`fixed top-0 right-0 bottom-0 w-[85%] max-w-sm z-[100] md:hidden bg-white shadow-2xl transition-transform duration-400 flex flex-col ${
          open ? 'translate-x-0' : 'translate-x-full'
        }`}
        style={{ fontFamily: '"Microsoft JhengHei", "微軟正黑體", "Noto Sans TC", sans-serif' }}
        role="dialog"
        aria-modal="true"
        aria-label="選單"
      >
        {/* Drawer header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-[#e7d9cb] bg-gradient-to-r from-[#fdf7ef] to-[#f7eee3] safe-top">
          <Logo size={32} textClassName="text-sm" />
          <button
            onClick={close}
            className="touch-target flex items-center justify-center rounded-full hover:bg-white/60 transition-colors -mr-2"
            aria-label="關閉選單"
          >
            <svg fill="none" viewBox="0 0 24 24" strokeWidth={2.2} stroke="currentColor" className="w-6 h-6 text-gray-700">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        {/* Primary nav */}
        <nav className="flex-1 overflow-y-auto py-3">
          {NAV_LINKS.map((link, i) => (
            <Link
              key={link.href}
              href={link.href}
              className="flex items-center gap-3 px-5 py-4 text-base font-bold text-gray-800 hover:bg-[#fdf7ef] active:bg-[#e7d9cb] transition-colors border-b border-gray-50"
              onClick={close}
              style={{
                opacity: open ? 1 : 0,
                transform: open ? 'translateX(0)' : 'translateX(12px)',
                transition: `opacity 0.3s ${i * 40 + 120}ms ease, transform 0.3s ${i * 40 + 120}ms ease`,
              }}
            >
              <span className="w-8 h-8 rounded-lg bg-[#fdf7ef] flex items-center justify-center shrink-0">
                {link.icon}
              </span>
              <span className="flex-1">{link.label}</span>
              <svg className="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          ))}

          <button
            onClick={() => { close(); setSearchOpen(true); }}
            className="w-full flex items-center gap-3 px-5 py-4 text-base font-bold text-gray-800 hover:bg-[#fdf7ef] active:bg-[#e7d9cb] transition-colors border-b border-gray-50"
          >
            <span className="w-8 h-8 rounded-lg bg-[#fdf7ef] flex items-center justify-center text-base"><SiteIcon name="search" size={16} /></span>
            <span className="flex-1 text-left">搜尋商品</span>
          </button>
        </nav>

        {/* Bottom actions */}
        <div className="border-t border-[#e7d9cb] bg-gradient-to-b from-[#fdf7ef] to-white px-5 py-4 space-y-2 safe-pb">
          {isLoggedIn ? (
            <>
              <Link
                href="/account"
                className="flex items-center gap-3 p-3 rounded-2xl bg-white border border-[#e7d9cb] hover:border-[#9F6B3E]/40 transition-colors"
                onClick={close}
              >
                <span className="w-9 h-9 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#b08257] flex items-center justify-center text-white shrink-0"><Icons.Seedling className="w-5 h-5" /></span>
                <div className="flex-1 min-w-0">
                  <div className="text-sm font-black text-gray-800 truncate">{customer?.name || '仙女'}</div>
                  <div className="text-[11px] text-[#9F6B3E] font-bold">我的仙女館 →</div>
                </div>
              </Link>
              <Link
                href="/order-lookup"
                className="flex items-center gap-2 px-3 py-2.5 text-sm font-bold text-gray-600 hover:text-[#9F6B3E] transition-colors"
                onClick={close}
              >
                <Icons.ShoppingBag className="w-4 h-4" />訂單查詢
              </Link>
              <button
                onClick={() => { logout(); close(); }}
                className="flex items-center gap-2 px-3 py-2.5 text-sm font-bold text-gray-400 hover:text-red-500 transition-colors"
              >
                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2} aria-hidden><path strokeLinecap="round" strokeLinejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9" /></svg>登出
              </button>
            </>
          ) : (
            <Link
              href="/account"
              className="flex items-center justify-center gap-2 w-full py-3 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-colors min-h-[48px]"
              onClick={close}
            >
              <Icons.Seedling className="w-5 h-5 inline-block" /> 登入 / 開始仙女任務
            </Link>
          )}

          <Link
            href="/cart"
            className="flex items-center justify-between w-full px-4 py-3 bg-white border border-[#e7d9cb] rounded-full font-black text-gray-800 hover:border-[#9F6B3E]/50 transition-colors min-h-[48px]"
            onClick={close}
          >
            <span className="flex items-center gap-2"><Icons.ShoppingBag className="w-5 h-5" /> 購物車</span>
            {itemCount > 0 ? (
              <span className="inline-flex items-center justify-center bg-[#9F6B3E] text-white text-xs font-black rounded-full min-w-[24px] h-6 px-2">
                {itemCount}
              </span>
            ) : (
              <span className="text-xs text-gray-400 font-normal">空的</span>
            )}
          </Link>
        </div>
      </aside>

      <SearchModal open={searchOpen} onClose={() => setSearchOpen(false)} />
    </>
  );
}
