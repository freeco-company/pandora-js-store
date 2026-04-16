'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';

const STORAGE_KEY = 'pandora-cookie-consent';

export default function CookieConsent() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const consent = localStorage.getItem(STORAGE_KEY);
    if (!consent) {
      // Delay slightly so it doesn't flash on hydration
      const timer = setTimeout(() => setVisible(true), 500);
      return () => clearTimeout(timer);
    }
  }, []);

  const accept = () => {
    localStorage.setItem(STORAGE_KEY, 'accepted');
    setVisible(false);
  };

  if (!visible) return null;

  return (
    <div className="fixed left-0 right-0 z-[80] p-4 sm:p-6 bottom-[calc(5rem+env(safe-area-inset-bottom))] md:bottom-0">
      <div className="max-w-[800px] mx-auto bg-white rounded-xl shadow-2xl border border-gray-200 p-5 sm:p-6 flex flex-col sm:flex-row items-start sm:items-center gap-4">
        <div className="flex-1 text-sm text-gray-600 leading-relaxed">
          本網站使用 Cookie 以提升您的瀏覽體驗並分析網站流量。繼續使用本網站即表示您同意我們的{' '}
          <Link href="/privacy" className="text-[#9F6B3E] hover:underline">
            隱私權條款
          </Link>
          。
        </div>
        <div className="flex gap-3 shrink-0">
          <button
            onClick={accept}
            className="px-6 py-2 bg-[#9F6B3E] text-white text-sm font-medium rounded-full hover:bg-[#85572F] transition-colors"
          >
            我知道了
          </button>
        </div>
      </div>
    </div>
  );
}
