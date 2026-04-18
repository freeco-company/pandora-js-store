'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { updateProfile, ApiError } from '@/lib/api';
import LogoLoader from '@/components/LogoLoader';

export default function CompleteProfilePage() {
  const router = useRouter();
  const { token, customer, isLoggedIn, loading: authLoading, setAuth } = useAuth();
  const needsEmail = !customer?.email || customer.email.endsWith('@line.user');
  const [name, setName] = useState(customer?.name || '');
  const [email, setEmail] = useState(needsEmail ? '' : (customer?.email || ''));
  const [phone, setPhone] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  if (authLoading) {
    return (
      <div className="flex items-center justify-center py-24 min-h-[60vh]">
        <LogoLoader size={80} />
      </div>
    );
  }

  if (!isLoggedIn || !token) {
    router.replace('/account');
    return null;
  }

  // Already complete — redirect away
  if (customer?.phone && customer?.email && !customer.email.endsWith('@line.user')) {
    router.replace('/account');
    return null;
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!name.trim()) {
      setError('請輸入姓名');
      return;
    }

    if (needsEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      setError('請輸入有效的 Email');
      return;
    }

    if (!/^09\d{8}$/.test(phone)) {
      setError('請輸入有效手機號碼（09 開頭，共 10 碼）');
      return;
    }

    setSaving(true);
    try {
      const payload: { name: string; phone: string; email?: string } = { name: name.trim(), phone };
      if (needsEmail) payload.email = email.trim().toLowerCase();
      const updated = await updateProfile(token, payload);
      setAuth(token, { ...customer!, name: updated.name, phone: updated.phone, email: updated.email });

      const redirect = sessionStorage.getItem('pandora-login-redirect');
      sessionStorage.removeItem('pandora-login-redirect');
      router.replace(redirect || '/account');
    } catch (err) {
      if (err instanceof ApiError) {
        // Prefer a field-specific message (email/phone unique, format) so
        // the user knows exactly what to fix. Fall back to backend's top-level
        // message, then a generic fallback.
        const fieldMsg = err.fieldError('email') || err.fieldError('phone') || err.fieldError('name');
        setError(fieldMsg || err.body.message || '儲存失敗，請稍後再試');
      } else {
        setError('儲存失敗，請稍後再試');
      }
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="max-w-md mx-auto p-5 sm:p-8 pb-24">
      <div className="text-center mb-8">
        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-[#fdf7ef] flex items-center justify-center">
          <svg width="32" height="32" viewBox="0 0 20 20" fill="none" aria-hidden>
            <path d="M10 18V10" stroke="#4A9D5F" strokeWidth="1.5" strokeLinecap="round" />
            <path d="M10 12C10 9 13 7 16 7c0 3-2 5-6 5z" fill="#4A9D5F" />
            <path d="M10 14C10 11 7 9 4 9c0 3 2 5 6 5z" fill="#6BC07E" />
          </svg>
        </div>
        <h1 className="text-2xl font-black text-slate-800 mb-2">歡迎加入仙女館</h1>
        <p className="text-sm text-slate-500">請完成以下資料，就能開始購物囉！</p>
      </div>

      <form onSubmit={handleSubmit} className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6 space-y-4">
        <div>
          <label className="block text-[11px] font-black text-slate-500 tracking-wider mb-1">姓名 *</label>
          <input
            type="text"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            maxLength={100}
            className="w-full px-4 py-3 rounded-xl border border-[#e7d9cb] bg-white focus:border-[#9F6B3E] focus:outline-none text-sm"
          />
        </div>

        {needsEmail && (
          <div>
            <label className="block text-[11px] font-black text-slate-500 tracking-wider mb-1">Email *</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="your@email.com"
              required
              maxLength={255}
              autoComplete="email"
              className="w-full px-4 py-3 rounded-xl border border-[#e7d9cb] bg-white focus:border-[#9F6B3E] focus:outline-none text-sm"
            />
            <p className="text-[10px] text-slate-400 mt-1">LINE 登入未提供 Email，請填寫以接收訂單通知</p>
          </div>
        )}

        <div>
          <label className="block text-[11px] font-black text-slate-500 tracking-wider mb-1">手機號碼 *</label>
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="09xxxxxxxx"
            required
            maxLength={10}
            className="w-full px-4 py-3 rounded-xl border border-[#e7d9cb] bg-white focus:border-[#9F6B3E] focus:outline-none text-sm"
          />
          <p className="text-[10px] text-slate-400 mt-1">用於訂單出貨通知，不會公開顯示</p>
        </div>

        {error && (
          <p className="text-sm text-red-500 font-bold">{error}</p>
        )}

        <button
          type="submit"
          disabled={saving}
          className="w-full h-12 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black shadow-md shadow-[#9F6B3E]/25 active:scale-[0.98] transition-transform disabled:opacity-50"
        >
          {saving ? '儲存中...' : '完成註冊'}
        </button>
      </form>
    </div>
  );
}
