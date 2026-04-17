'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import Icons from '@/components/SvgIcons';
import { useAuth } from '@/components/AuthProvider';
import { useCelebrate } from '@/components/Celebration';
import { useSerendipity } from '@/components/Serendipity';
import Mascot from '@/components/Mascot';
import LogoLoader from '@/components/LogoLoader';
import ActivationQuest from '@/components/ActivationQuest';
import {
  getCustomerDashboard,
  type CustomerDashboard,
} from '@/lib/api';
import { ACHIEVEMENT_CATALOG, stageFromStreak, TIER_GRADIENTS } from '@/lib/achievements';

export default function AccountPage() {
  const { token, isLoggedIn, loading, login, logout, customer } = useAuth();
  const { celebrateMany } = useCelebrate();
  const { show: showSerendipity } = useSerendipity();

  const [data, setData] = useState<CustomerDashboard | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    let cancelled = false;

    getCustomerDashboard(token)
      .then((d) => {
        if (cancelled) return;
        setData(d);
        celebrateMany(d._achievements, d._outfits);
        if (d._serendipity) showSerendipity(d._serendipity);
      })
      .catch(() => setError('資料取得失敗'));

    return () => {
      cancelled = true;
    };
  }, [token, isLoggedIn, celebrateMany, showSerendipity]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-24 min-h-[60vh]">
        <LogoLoader size={80} />
      </div>
    );
  }

  if (!isLoggedIn) {
    const previewAchievements = Object.values(ACHIEVEMENT_CATALOG).slice(0, 12);
    return (
      <div className="max-w-2xl mx-auto p-4 sm:p-6 pb-24 space-y-8">
        {/* Hero login */}
        <section className="bg-gradient-to-br from-[#f7eee3] via-[#fdf7ef] to-[#f7eee3] rounded-3xl p-8 sm:p-10 text-center relative overflow-hidden">
          <div className="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-[#9F6B3E]/10 blur-3xl" />
          <div className="relative">
            <div className="mb-5 flex justify-center">
              <Mascot stage="sprout" mood="happy" size={120} />
            </div>
            <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">SPROUT · 芽芽任務</div>
            <h1 className="text-2xl sm:text-3xl font-black text-slate-800 mb-3">仙女登入專區</h1>
            <p className="text-sm text-slate-600 mb-6 max-w-sm mx-auto leading-relaxed">
              登入後解鎖 <strong className="text-[#9F6B3E]">{Object.keys(ACHIEVEMENT_CATALOG).length} 種成就</strong>、10+ 服裝、連續造訪 streak、芽芽成長系統
            </p>
            <button
              onClick={login}
              className="px-7 py-3.5 rounded-full bg-[#9F6B3E] text-white font-black hover:bg-[#85572F] transition-colors min-h-[52px] shadow-lg shadow-[#9F6B3E]/30"
            >
              <Icons.Seedling className="w-5 h-5 inline-block" /> 使用 Google 登入
            </button>
          </div>
        </section>

        {/* Preview — achievements grid (locked) */}
        <section>
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-sm font-black text-slate-800 flex items-center gap-2">
              <Icons.Trophy className="w-5 h-5 inline-block" /> 你可以解鎖的成就
            </h2>
            <span className="text-[10px] font-bold text-slate-400">全部 {Object.keys(ACHIEVEMENT_CATALOG).length} 個</span>
          </div>
          <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
            {previewAchievements.map((def) => (
              <div
                key={def.code}
                className="aspect-square rounded-2xl p-2 flex flex-col items-center justify-center text-center bg-slate-100 opacity-60 relative overflow-hidden"
              >
                <div className="text-3xl mb-1 grayscale">{def.emoji}</div>
                <div className="text-[10px] font-black leading-tight text-slate-400">{def.name}</div>
                <div className="absolute top-1.5 right-1.5"><Icons.Lock className="w-3.5 h-3.5 text-slate-400" /></div>
              </div>
            ))}
          </div>
          <p className="text-xs text-slate-400 text-center mt-4">登入後所有成就自動解鎖進度</p>
        </section>

        {/* Benefits list */}
        <section className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e7d9cb]">
          <h2 className="text-base font-black text-slate-800 mb-4 flex items-center gap-2"><Icons.Ribbon className="w-5 h-5 text-[#9F6B3E]" /> 加入仙女館好處</h2>
          <ul className="space-y-3 text-sm text-slate-700">
            {([
              { icon: <Icons.Fire className="w-5 h-5 text-[#9F6B3E]" />, title: '連續造訪 Streak', desc: '7/30/100 天解鎖獎章與稀有服裝' },
              { icon: <Icons.Ribbon className="w-5 h-5 text-[#9F6B3E]" />, title: '成就收集', desc: '首購、VIP 解鎖、類別探索⋯各種事件都有回饋' },
              { icon: <Icons.Crown className="w-5 h-5 text-[#9F6B3E]" />, title: '芽芽衣櫃', desc: '橡實帽、花冠、珍珠項鍊⋯換裝展現你的風格' },
              { icon: <Icons.Sparkles className="w-5 h-5 text-[#9F6B3E]" />, title: '驚喜彩蛋', desc: '隨機出現的芽芽訊息，偶爾送上專屬暖心話' },
              { icon: <Icons.ShoppingBag className="w-5 h-5 text-[#9F6B3E]" />, title: '訂單查詢', desc: '隨時查看訂單狀態、歷史紀錄' },
              { icon: <Icons.Diamond className="w-5 h-5 text-[#9F6B3E]" />, title: 'VIP 價', desc: '搭配滿 $4,000 自動升級最優惠' },
            ] as const).map((b, i) => (
              <li key={i} className="flex items-start gap-3">
                <span className="w-9 h-9 rounded-xl bg-[#fdf7ef] flex items-center justify-center shrink-0">
                  {b.icon}
                </span>
                <div>
                  <div className="font-black text-slate-800 text-sm">{b.title}</div>
                  <div className="text-xs text-slate-500 mt-0.5">{b.desc}</div>
                </div>
              </li>
            ))}
          </ul>
        </section>

        {/* Secondary CTA */}
        <section className="text-center py-4">
          <button
            onClick={login}
            className="px-8 py-3.5 rounded-full bg-[#9F6B3E] text-white font-black hover:bg-[#85572F] transition-colors min-h-[52px] shadow-lg shadow-[#9F6B3E]/30"
          >
            <Icons.Seedling className="w-5 h-5 inline-block" /> 立即登入解鎖
          </button>
          <div className="mt-4">
            <Link href="/products" className="text-xs font-bold text-slate-500 hover:text-[#9F6B3E] transition-colors">
              先逛商品 →
            </Link>
          </div>
        </section>
      </div>
    );
  }

  if (error) {
    return <div className="max-w-2xl mx-auto p-8 text-center text-red-500">{error}</div>;
  }

  if (!data) {
    return (
      <div className="flex items-center justify-center py-24 min-h-[60vh]">
        <LogoLoader size={80} />
      </div>
    );
  }

  const stage = stageFromStreak(data.customer.streak_days);
  const ownedCodes = new Set(data.outfits.owned.map((o) => o.code));
  const { level, xp_in_level, total_xp, referral_code } = data.customer;

  const chevron = (
    <svg className="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
    </svg>
  );

  return (
    <div className="max-w-2xl mx-auto p-4 sm:p-6 space-y-5 pb-24">
      {/* Hero: mascot + streak — tap to enter immersive page */}
      <Link
        href="/account/mascot"
        className="block bg-gradient-to-br from-[#e7d9cb] to-[#f7eee3] rounded-3xl p-6 flex items-center gap-4 hover:shadow-lg hover:-translate-y-0.5 transition-all group"
        aria-label="進入芽芽之家"
      >
        <Mascot
          stage={stage}
          mood={data.customer.streak_days >= 3 ? 'excited' : 'happy'}
          size={96}
          outfit={data.customer.current_outfit}
          backdrop={data.customer.current_backdrop}
        />
        <div className="flex-1 min-w-0">
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">哈囉仙女</div>
          <h1 className="text-xl font-black text-slate-800 truncate">{data.customer.name || customer?.name}</h1>
          <div className="mt-2 flex items-center gap-3 text-[11px] font-black text-slate-600">
            <span className="inline-flex items-center gap-0.5"><Icons.Fire className="w-3.5 h-3.5" /> {data.customer.streak_days} 天</span>
            <span className="inline-flex items-center gap-0.5"><Icons.ShoppingBag className="w-3.5 h-3.5" /> {data.customer.total_orders} 單</span>
            <span className="inline-flex items-center gap-0.5"><Icons.Star className="w-3.5 h-3.5" /> Lv.{level}</span>
          </div>
          {/* XP progress bar */}
          <div className="mt-2">
            <div className="flex items-center justify-between text-[10px] font-bold text-[#7a5836] mb-0.5">
              <span>{total_xp} XP</span>
              <span>{xp_in_level}/100 → Lv.{level + 1}</span>
            </div>
            <div className="h-1.5 bg-white/60 rounded-full overflow-hidden">
              <div
                className="h-full bg-gradient-to-r from-[#9F6B3E] to-[#E8A93B] rounded-full transition-all duration-500"
                style={{ width: `${xp_in_level}%` }}
              />
            </div>
          </div>
          <div className="mt-3 inline-flex items-center gap-1 text-[11px] font-black text-[#9F6B3E] opacity-70 group-hover:opacity-100 group-hover:translate-x-1 transition-all">
            進入芽芽之家 →
          </div>
        </div>
      </Link>

      {/* Activation quest (hidden when all done) */}
      <ActivationQuest progress={data.customer.activation_progress} />

      {/* Referral share card */}
      {referral_code && (
        <section className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb] rounded-3xl p-5">
          <div className="flex items-center gap-4">
            <div className="w-14 h-14 rounded-2xl bg-white flex items-center justify-center shadow-sm shrink-0"><Icons.Gift className="w-7 h-7 text-[#9F6B3E]" /></div>
            <div className="flex-1 min-w-0">
              <div className="text-sm font-black text-slate-800">邀請朋友一起當仙女</div>
              <div className="text-[11px] text-slate-500 mt-0.5">分享推薦碼，雙方都可獲得成就經驗值</div>
              <div className="mt-2 flex items-center gap-2">
                <code className="px-3 py-1 bg-white rounded-lg text-sm font-black text-[#9F6B3E] tracking-widest border border-[#e7d9cb]">
                  {referral_code}
                </code>
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(`${window.location.origin}/?ref=${referral_code}`);
                    alert('已複製推薦連結！');
                  }}
                  className="text-[11px] font-bold text-[#9F6B3E] underline active:scale-95"
                >
                  複製連結
                </button>
              </div>
            </div>
          </div>
        </section>
      )}

      {/* Quick links: mascot wardrobe + recap */}
      <div className="grid grid-cols-2 gap-3">
        <Link
          href="/account/mascot"
          className="group bg-white border border-[#e7d9cb] rounded-2xl p-4 hover:shadow-md transition-all text-center"
        >
          <div className="mb-1"><Icons.Crown className="w-8 h-8 text-[#9F6B3E]" /></div>
          <div className="text-xs font-black text-slate-800">芽芽衣櫃</div>
          <div className="text-[10px] text-slate-400 mt-0.5">
            {Object.keys(data.outfits.catalog).filter((c) => ownedCodes.has(c)).length}/{Object.keys(data.outfits.catalog).length} 服裝
          </div>
        </Link>
        <Link
          href="/account/recap"
          className="group bg-white border border-[#e7d9cb] rounded-2xl p-4 hover:shadow-md transition-all text-center"
        >
          <div className="mb-1"><Icons.TierSteps className="w-8 h-8 text-[#9F6B3E]" /></div>
          <div className="text-xs font-black text-slate-800">月度回顧</div>
          <div className="text-[10px] text-slate-400 mt-0.5">消費分析 + 成就進度</div>
        </Link>
      </div>

      {/* Settings / account management links */}
      <section className="bg-white rounded-3xl border border-[#e7d9cb] overflow-hidden divide-y divide-[#e7d9cb]">
        <Link href="/account/referral" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-9 h-9 rounded-xl bg-[#fdf7ef] flex items-center justify-center shrink-0"><Icons.Gift className="w-5 h-5 text-[#9F6B3E]" /></div>
          <div className="flex-1 text-sm font-black text-slate-800">推薦好友</div>
          {chevron}
        </Link>
        <Link href="/account/profile" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-9 h-9 rounded-xl bg-[#fdf7ef] flex items-center justify-center shrink-0"><Icons.Shield className="w-5 h-5 text-[#9F6B3E]" /></div>
          <div className="flex-1 text-sm font-black text-slate-800">個人資料</div>
          {chevron}
        </Link>
        <Link href="/account/addresses" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-9 h-9 rounded-xl bg-[#fdf7ef] flex items-center justify-center shrink-0"><Icons.Star className="w-5 h-5 text-[#9F6B3E]" /></div>
          <div className="flex-1 text-sm font-black text-slate-800">常用地址</div>
          {chevron}
        </Link>
        <Link href="/order-lookup" className="flex items-center gap-3 px-5 py-4 active:bg-[#fdf7ef]">
          <div className="w-9 h-9 rounded-xl bg-[#fdf7ef] flex items-center justify-center shrink-0"><Icons.ShoppingBag className="w-5 h-5 text-[#9F6B3E]" /></div>
          <div className="flex-1 text-sm font-black text-slate-800">訂單紀錄</div>
          {chevron}
        </Link>
      </section>

      <div className="pt-2 text-center">
        <button onClick={logout} className="text-[12px] font-bold text-slate-400 underline">
          登出
        </button>
      </div>
    </div>
  );
}

