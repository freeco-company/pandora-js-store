'use client';

import { useState } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import Icons from './SvgIcons';

const HIDE_ON_PATHS = ['/cart', '/checkout', '/order-complete'];

export default function Footer() {
  const pathname = usePathname() || '/';
  if (HIDE_ON_PATHS.some((p) => pathname === p || pathname.startsWith(p + '/'))) {
    return null;
  }
  return <FooterInner />;
}

function FooterInner() {
  const [email, setEmail] = useState('');
  const [subscribed, setSubscribed] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  const handleSubscribe = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email || submitting) return;
    setSubmitting(true);
    setError('');
    try {
      const res = await fetch('/api/subscriptions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      if (!res.ok) throw new Error('subscribe failed');
      setSubscribed(true);
      setEmail('');
    } catch {
      setError('訂閱失敗，請稍後再試。');
    } finally {
      setSubmitting(false);
    }
  };
  return (
    <footer className="mt-auto pb-[calc(5rem+env(safe-area-inset-bottom))] md:pb-0" style={{ backgroundColor: '#1e1e1e' }}>
      {/* Trust strip — 官方授權 / 詐騙警語 / 合規聲明 */}
      <div className="bg-gradient-to-b md:bg-gradient-to-r from-[#2a1f17] via-[#1e1e1e] to-[#2a1f17] border-b border-white/5">
        <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-5 grid grid-cols-1 md:grid-cols-3 gap-4">
          {([
            { icon: <Icons.Lock className="w-4 h-4 text-[#e7d9cb] icon-shimmer" />, title: 'JEROSSE 官方正品授權', desc: '法芮可有限公司授權經銷，每件商品皆為原廠出貨' },
            { icon: <Icons.AlertTriangle className="w-4 h-4 text-[#E8A93B] icon-pulse" />, title: '小心詐騙電商', desc: '認明官方域名 pandora.js-store.com.tw' },
            { icon: <Icons.CheckCircle className="w-4 h-4 text-[#4A9D5F] icon-float" />, title: '食品合規聲明', desc: '本站商品為食品，非藥品，不具醫療療效' },
          ]).map(({ icon, title, desc }) => (
            <div key={title} className="flex items-center gap-3 md:items-start px-2 py-1.5 md:p-0">
              <span className="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg bg-white/5">{icon}</span>
              <div className="min-w-0">
                <div className="text-white text-[13px] font-bold">{title}</div>
                <div className="text-gray-400 text-[11px] mt-0.5 leading-snug">{desc}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
      <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Brand & Company Info */}
          <div>
            <h3
              className="text-lg font-bold text-white mb-3"
            >
              婕樂纖仙女館
            </h3>
            <p className="text-sm text-gray-400 leading-relaxed mb-4">
              JEROSSE 婕樂纖官方正品授權經銷商。提供最優質的健康美麗產品，讓每位女性都能綻放自信光彩。
            </p>
            <div className="text-sm text-gray-400 space-y-1.5">
              <p>產品由 <span className="text-gray-300">法芮可有限公司</span> 提供</p>
              <p>統一編號：90445399</p>
              <p>公司地址：110 臺北市信義區忠孝東路五段 510 號 26 樓</p>
              <p>
                客服信箱：
                <a href="mailto:contact@freeco.cc" className="text-gray-300 hover:text-white transition-colors">
                  contact@freeco.cc
                </a>
              </p>
              <p>
                免費諮詢：
                <a
                  href="https://www.instagram.com/pandorasdo/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-gray-300 hover:text-white transition-colors"
                >
                  Instagram @pandorasdo
                </a>
              </p>
            </div>
          </div>

          {/* Links */}
          <div>
            <h4 className="text-sm font-semibold text-white mb-3">資訊</h4>
            <ul className="space-y-2 text-sm text-gray-400">
              <li>
                <Link href="/faq" className="hover:text-white transition-colors">
                  常見問題
                </Link>
              </li>
              <li>
                <Link href="/return-policy" className="hover:text-white transition-colors">
                  退換貨政策
                </Link>
              </li>
              <li>
                <Link href="/privacy" className="hover:text-white transition-colors">
                  隱私權條款
                </Link>
              </li>
              <li>
                <Link href="/terms" className="hover:text-white transition-colors">
                  服務條款
                </Link>
              </li>
              <li>
                <Link href="/order-lookup" className="hover:text-white transition-colors">
                  訂單查詢
                </Link>
              </li>
              <li>
                <Link href="/reviews" className="hover:text-white transition-colors">
                  買家好評
                </Link>
              </li>
              <li>
                <Link href="/where-to-buy" className="hover:text-white transition-colors">
                  哪裡買
                </Link>
              </li>
            </ul>
          </div>

          {/* Shop by Category */}
          <div>
            <h4 className="text-sm font-semibold text-white mb-3">商品分類</h4>
            <ul className="space-y-2 text-sm text-gray-400">
              <li><Link href="/products/category/healthy-vitality-series" className="hover:text-white transition-colors">健康活力系列</Link></li>
              <li><Link href="/products/category/functional-health-series" className="hover:text-white transition-colors">健康維持系列</Link></li>
              <li><Link href="/products/category/body-beauty-series" className="hover:text-white transition-colors">美容美體系列</Link></li>
              <li><Link href="/products/category/slimming" className="hover:text-white transition-colors">體重管理</Link></li>
              <li><Link href="/products" className="hover:text-white transition-colors">全部商品 →</Link></li>
            </ul>
          </div>

          {/* Social */}
          <div>
            <h4 className="text-sm font-semibold text-white mb-3">追蹤我們</h4>
            <div className="flex gap-4">
              <a
                href="https://www.instagram.com/pandorasdo/"
                target="_blank"
                rel="noopener noreferrer"
                className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#9F6B3E] transition-colors text-gray-400 hover:text-white"
                aria-label="Instagram"
              >
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z" />
                </svg>
              </a>
              <a
                href="https://lin.ee/62wj7qa"
                target="_blank"
                rel="noopener noreferrer"
                className="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-[#9F6B3E] transition-colors text-gray-400 hover:text-white"
                aria-label="LINE 官方帳號"
              >
                <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314" />
                </svg>
              </a>
            </div>
          </div>
        </div>

        {/* Email Subscription */}
        <div className="border-t border-white/10 mt-8 pt-8">
          <div className="max-w-md mx-auto text-center">
            <h4 className="text-sm font-semibold text-white mb-2">訂閱最新消息</h4>
            <p className="text-xs text-gray-400 mb-4">搶先獲得新品上架與優惠資訊</p>
            {subscribed ? (
              <p className="text-sm text-[#06C755] font-medium">訂閱成功！</p>
            ) : error ? (
              <p className="text-sm text-red-400 font-medium">{error}</p>
            ) : (
              <form onSubmit={handleSubscribe} className="flex gap-2">
                <input
                  type="email"
                  required
                  placeholder="輸入 Email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="flex-1 min-w-0 px-4 py-2 bg-white/10 border border-white/10 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-[#9F6B3E] transition-colors"
                />
                <button
                  type="submit"
                  disabled={submitting}
                  className="px-5 py-2 bg-[#9F6B3E] hover:bg-[#8a5c34] disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors whitespace-nowrap"
                >
                  訂閱
                </button>
              </form>
            )}
          </div>
        </div>

        <div className="border-t border-white/10 mt-8 pt-8 text-center text-sm text-gray-500">
          <div>&copy; {new Date().getFullYear()} 婕樂纖仙女館 JEROSSE. All rights reserved.</div>
          <div className="mt-1 text-[10px] text-gray-600">v{process.env.NEXT_PUBLIC_APP_VERSION || '2.0.0'}</div>
        </div>
      </div>
    </footer>
  );
}
