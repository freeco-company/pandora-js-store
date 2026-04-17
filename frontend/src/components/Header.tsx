'use client';

import Link from 'next/link';
import { useState, useEffect, useCallback, useRef } from 'react';
import { useCart } from './CartProvider';
import { useAuth } from './AuthProvider';
import { useMobileMenu } from './MobileMenuContext';
import SearchModal from './SearchModal';
import Logo from './Logo';
import SiteIcon from './SiteIcon';

const NAV_LINKS = [
  { href: '/', label: '首頁' },
  { href: '/products', label: '全館商品' },
  { href: '/articles', label: '專欄文章' },
  { href: '/about', label: '關於 FP' },
  { href: '/join', label: '加入我們' },
];

export default function Header() {
  const [scrolled, setScrolled] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const { itemCount } = useCart();
  const { isLoggedIn, customer, logout } = useAuth();
  const { open: menuOpen, setOpen: setMenuOpen } = useMobileMenu();
  const accountRef = useRef<HTMLDivElement>(null);
  const closeSearch = useCallback(() => setSearchOpen(false), []);
  const cartBadgeRef = useRef<HTMLSpanElement>(null);

  useEffect(() => {
    const handleScroll = () => {
      setScrolled(window.scrollY > 20);
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    return () => window.removeEventListener('scroll', handleScroll);
  }, []);

  useEffect(() => {
    const handleCartAdded = () => {
      const badge = cartBadgeRef.current;
      if (!badge) return;
      badge.classList.add('cart-badge-pulse');
      const onEnd = () => badge.classList.remove('cart-badge-pulse');
      badge.addEventListener('animationend', onEnd, { once: true });
      setTimeout(() => badge.classList.remove('cart-badge-pulse'), 450);
    };
    window.addEventListener('cart-item-added', handleCartAdded);
    return () => window.removeEventListener('cart-item-added', handleCartAdded);
  }, []);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (accountRef.current && !accountRef.current.contains(e.target as Node)) {
        setAccountOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <header
      className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${
        scrolled ? 'shadow-md bg-[#e7d9cb]/90 backdrop-blur-md' : 'bg-[#e7d9cb]'
      }`}
    >
      <div className="max-w-[1290px] mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-[64px] md:h-[80px]">
          {/* Logo */}
          <Link href="/" className="flex items-center shrink-0" aria-label="婕樂纖仙女館首頁">
            <Logo size={36} textClassName="text-[14px] md:text-[18px]" />
          </Link>

          {/* Desktop Nav */}
          <nav className="hidden md:flex items-center gap-7">
            {NAV_LINKS.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                className="text-sm font-bold text-gray-800 hover:text-[#9F6B3E] transition-colors relative group"
              >
                {link.label}
                <span className="absolute -bottom-1 left-0 right-0 h-0.5 bg-[#9F6B3E] origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-300" />
              </Link>
            ))}
          </nav>

          {/* Right icons */}
          <div className="flex items-center gap-1 sm:gap-2">
            {/* Search — desktop only */}
            <button
              className="hidden md:flex touch-target items-center justify-center text-gray-700 hover:text-[#9F6B3E] transition-colors"
              aria-label="搜尋"
              onClick={() => setSearchOpen(true)}
            >
              <svg fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="w-5 h-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
              </svg>
            </button>

            {/* Account — desktop only */}
            <div className="hidden md:flex relative" ref={accountRef}>
              {isLoggedIn ? (
                <>
                  <button
                    onClick={() => setAccountOpen(!accountOpen)}
                    className="flex items-center gap-1.5 touch-target px-2 text-gray-700 hover:text-[#9F6B3E] transition-colors"
                    aria-label="帳號"
                  >
                    <svg fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="w-5 h-5">
                      <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    <span className="text-sm font-medium max-w-[80px] truncate">{customer?.name}</span>
                  </button>
                  {accountOpen && (
                    <div className="absolute right-0 top-full mt-2 w-44 bg-white rounded-xl shadow-xl border border-[#e7d9cb] py-1 z-50">
                      <Link
                        href="/account"
                        className="block px-4 py-2 text-sm font-bold text-gray-700 hover:bg-[#fdf7ef] transition-colors"
                        onClick={() => setAccountOpen(false)}
                      >
                        <SiteIcon name="sprout" size={14} className="inline -mt-0.5" /> 我的仙女館
                      </Link>
                      <Link
                        href="/order-lookup"
                        className="block px-4 py-2 text-sm text-gray-700 hover:bg-[#fdf7ef] transition-colors"
                        onClick={() => setAccountOpen(false)}
                      >
                        訂單查詢
                      </Link>
                      <button
                        onClick={() => { logout(); setAccountOpen(false); }}
                        className="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-[#fdf7ef] transition-colors"
                      >
                        登出
                      </button>
                    </div>
                  )}
                </>
              ) : (
                <Link href="/account" className="touch-target flex items-center justify-center text-gray-700 hover:text-[#9F6B3E] transition-colors" aria-label="登入">
                  <svg fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="w-5 h-5">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                  </svg>
                </Link>
              )}
            </div>

            {/* Cart — desktop only (mobile has it in bottom dock) */}
            <Link href="/cart" data-cart-icon className="hidden md:flex relative touch-target items-center justify-center text-gray-700 hover:text-[#9F6B3E] transition-colors" aria-label="購物車">
              <svg fill="none" viewBox="0 0 24 24" strokeWidth={1.8} stroke="currentColor" className="w-5 h-5">
                <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
              </svg>
              {itemCount > 0 && (
                <span
                  ref={cartBadgeRef}
                  className="absolute -top-0.5 -right-0.5 bg-[#9F6B3E] text-white text-[10px] font-black rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
                >
                  {itemCount > 99 ? '99+' : itemCount}
                </span>
              )}
            </Link>
          </div>
        </div>
      </div>

      <SearchModal open={searchOpen} onClose={closeSearch} />
    </header>
  );
}
