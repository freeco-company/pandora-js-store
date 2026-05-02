'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { useToast } from '@/components/Toast';
import LogoLoader from '@/components/LogoLoader';
import SiteIcon from '@/components/SiteIcon';
import { getProfile, updateProfile, type CustomerProfile } from '@/lib/api';

export default function ProfilePage() {
  const router = useRouter();
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const { toast } = useToast();
  const [profile, setProfile] = useState<CustomerProfile | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');

  useEffect(() => {
    if (authLoading) return;
    if (!isLoggedIn || !token) {
      router.replace('/account');
      return;
    }
    getProfile(token)
      .then((p) => {
        setProfile(p);
        setName(p.name || '');
        setPhone(p.phone || '');
      })
      .finally(() => setLoading(false));
  }, [token, isLoggedIn, authLoading, router]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;
    setSaving(true);
    try {
      const updated = await updateProfile(token, { name: name.trim(), phone: phone.trim() || undefined });
      setProfile(updated);
      toast('個人資料已更新');
    } catch {
      toast('更新失敗，請稍後再試');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="py-24 flex justify-center">
        <LogoLoader size={72} />
      </div>
    );
  }
  if (!profile) return null;

  return (
    <div className="max-w-2xl mx-auto p-4 sm:p-6 space-y-5 pb-24">
      {/* Header */}
      <div className="flex items-center gap-2 mb-2">
        <Link href="/account" className="text-[#9F6B3E] text-sm font-black">
          ← 我的仙女館
        </Link>
      </div>

      <h1 className="text-2xl font-black text-slate-800">個人資料</h1>

      <form onSubmit={handleSubmit} className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6 space-y-4">
        {/* Login provider badge */}
        <div className="flex items-center gap-3 px-4 py-3 rounded-xl bg-slate-50">
          {profile.auth_provider === 'line' ? (
            <>
              <span className="w-8 h-8 rounded-full bg-[#06C755] flex items-center justify-center shrink-0">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.48 2 2 5.82 2 10.5c0 4.21 3.74 7.74 8.79 8.4.34.07.81.22.93.51.1.26.07.67.03.94l-.15.9c-.05.28-.22 1.1.96.6s6.37-3.75 8.69-6.42C23.23 13.27 22 11.07 22 10.5 22 5.82 17.52 2 12 2z"/></svg>
              </span>
              <div>
                <div className="text-sm font-black text-slate-800">LINE 登入</div>
                <div className="text-[10px] text-slate-400">透過 LINE 帳號登入</div>
              </div>
            </>
          ) : (
            <>
              <span className="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center shrink-0">
                <svg width="16" height="16" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
              </span>
              <div>
                <div className="text-sm font-black text-slate-800">Google 登入</div>
                <div className="text-[10px] text-slate-400">{profile.email}</div>
              </div>
            </>
          )}
        </div>

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

        <div>
          <label className="block text-[11px] font-black text-slate-500 tracking-wider mb-1">手機號碼</label>
          <input
            type="tel"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="09xxxxxxxx"
            maxLength={30}
            className="w-full px-4 py-3 rounded-xl border border-[#e7d9cb] bg-white focus:border-[#9F6B3E] focus:outline-none text-sm"
          />
          <p className="text-[10px] text-slate-400 mt-1">主要用於訂單出貨通知</p>
        </div>

        <button
          type="submit"
          disabled={saving}
          className="w-full h-12 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black shadow-md shadow-[#9F6B3E]/25 active:scale-[0.98] transition-transform disabled:opacity-50"
        >
          {saving ? '儲存中...' : '儲存變更'}
        </button>
      </form>

      {/* Settings shortcuts */}
      <div className="bg-white rounded-3xl border border-[#e7d9cb] overflow-hidden divide-y divide-[#e7d9cb]">
        <Link href="/account/addresses" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-10 h-10 rounded-2xl bg-[#fdf7ef] flex items-center justify-center text-xl shrink-0"><SiteIcon name="target" size={20} className="text-[#9F6B3E]" /></div>
          <div className="flex-1">
            <div className="text-sm font-black text-slate-800">常用地址</div>
            <div className="text-[11px] text-slate-500">管理收件地址、預設地址</div>
          </div>
          <svg className="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
        <Link href="/order-lookup" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-10 h-10 rounded-2xl bg-[#fdf7ef] flex items-center justify-center text-xl shrink-0"><SiteIcon name="package" size={20} className="text-[#9F6B3E]" /></div>
          <div className="flex-1">
            <div className="text-sm font-black text-slate-800">訂單紀錄</div>
            <div className="text-[11px] text-slate-500">共 {profile.total_orders} 筆 · 累積 NT${profile.total_spent.toLocaleString()}</div>
          </div>
          <svg className="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
        <Link href="/account/mascot" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-10 h-10 rounded-2xl bg-[#fdf7ef] flex items-center justify-center shrink-0"><SiteIcon name="sprout" size={20} /></div>
          <div className="flex-1">
            <div className="text-sm font-black text-slate-800">寵物之家</div>
            <div className="text-[11px] text-slate-500">換裝、背景、成就</div>
          </div>
          <svg className="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      </div>
    </div>
  );
}
