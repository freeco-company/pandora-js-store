'use client';

import { useAuth } from '@/components/AuthProvider';
import { useState } from 'react';

export default function LineLoginButton() {
  const { loginWithLine } = useAuth();
  const [loading, setLoading] = useState(false);

  const handleClick = () => {
    setLoading(true);
    loginWithLine();
  };

  return (
    <button
      onClick={handleClick}
      disabled={loading}
      className="w-full inline-flex items-center justify-center gap-3 px-6 py-3 bg-[#06C755] border border-[#06C755] rounded-full font-medium text-white hover:bg-[#05b34d] transition-colors shadow-sm disabled:opacity-60 disabled:cursor-not-allowed"
    >
      {/* LINE Logo SVG */}
      <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M12 2C6.48 2 2 5.82 2 10.5c0 4.21 3.74 7.74 8.79 8.4.34.07.81.22.93.51.1.26.07.67.03.94l-.15.9c-.05.28-.22 1.1.96.6s6.37-3.75 8.69-6.42C23.23 13.27 22 11.07 22 10.5 22 5.82 17.52 2 12 2zm-3.03 11.04h-2.2a.46.46 0 0 1-.46-.46V8.65c0-.25.2-.46.46-.46s.46.2.46.46v3.47h1.74c.25 0 .46.2.46.46s-.21.46-.46.46zm1.7-.46a.46.46 0 0 1-.92 0V8.65a.46.46 0 0 1 .92 0v3.93zm4.55 0a.46.46 0 0 1-.32.44.46.46 0 0 1-.5-.14l-2.13-2.9v2.6a.46.46 0 0 1-.92 0V8.65a.46.46 0 0 1 .32-.44.46.46 0 0 1 .5.14l2.13 2.9v-2.6a.46.46 0 0 1 .92 0v3.93zm3.18-2.75c.25 0 .46.21.46.46s-.2.46-.46.46h-1.74v1.08h1.74c.25 0 .46.2.46.46s-.2.46-.46.46h-2.2a.46.46 0 0 1-.46-.46V8.65c0-.25.2-.46.46-.46h2.2c.25 0 .46.2.46.46s-.2.46-.46.46h-1.74v1.08h1.74z" />
      </svg>
      {loading ? '跳轉中...' : '使用 LINE 登入'}
    </button>
  );
}
