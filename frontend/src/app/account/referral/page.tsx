'use client';

/**
 * /account/referral — the member's personal referral code.
 * Reward is EXP-based (achievements), not money: prevents fraud risk and
 * keeps the gamification flywheel turning.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { SITE_URL } from '@/lib/site';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { useToast } from '@/components/Toast';
import LogoLoader from '@/components/LogoLoader';
import SiteIcon from '@/components/SiteIcon';
import { API_URL } from '@/lib/api';

interface ReferralState {
  referral_code: string;
  referrals_count: number;
  referrals_success: number;
}

export default function ReferralPage() {
  const router = useRouter();
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const { toast } = useToast();
  const [data, setData] = useState<ReferralState | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (authLoading) return;
    if (!isLoggedIn || !token) { router.replace('/account'); return; }
    fetch(`${API_URL}/customer/profile`, {
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((d) => setData(d))
      .finally(() => setLoading(false));
  }, [token, isLoggedIn, authLoading, router]);

  if (loading) return <div className="py-24 flex justify-center"><LogoLoader size={72} /></div>;
  if (!data) return null;

  const siteUrl = SITE_URL;
  const link = `${siteUrl}/?ref=${data.referral_code}`;
  const shareText = `我用婕樂纖仙女館享健康仙女生活！用我的邀請碼 ${data.referral_code} 一起加入 → ${link}`;

  const copy = async (text: string) => {
    try { await navigator.clipboard.writeText(text); toast('✓ 已複製'); }
    catch { toast('複製失敗'); }
  };

  return (
    <div className="max-w-2xl mx-auto p-4 sm:p-6 pb-24 space-y-5">
      <div>
        <Link href="/account" className="text-[#9F6B3E] text-sm font-black">← 我的仙女館</Link>
      </div>

      {/* Hero code card */}
      <div className="bg-gradient-to-br from-[#9F6B3E] via-[#8d5f36] to-[#6b4424] text-white rounded-3xl p-6 sm:p-8 text-center relative overflow-hidden">
        <span className="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#fcd561]/10 blur-2xl" />
        <div className="relative">
          <div className="text-[10px] font-black tracking-[0.3em] text-[#fcd561]">REFERRAL CODE</div>
          <h1 className="text-xl font-black mt-2">我的邀請碼</h1>
          <div
            onClick={() => copy(data.referral_code)}
            className="mt-4 mx-auto inline-block px-8 py-4 rounded-2xl bg-white/10 backdrop-blur border border-white/20 cursor-pointer hover:bg-white/15 transition-colors"
          >
            <div className="text-3xl sm:text-4xl font-black tracking-[0.2em]">{data.referral_code}</div>
            <div className="text-[10px] text-white/60 mt-1">點擊複製</div>
          </div>
        </div>
      </div>

      {/* Stats */}
      <section className="grid grid-cols-2 gap-3">
        <StatCard label="已邀請人數" value={`${data.referrals_count} 位`} emoji="handshake" />
        <StatCard label="首單完成" value={`${data.referrals_success} 位`} emoji="check-circle" />
      </section>

      {/* Mascot reward milestones — every successful referral feeds XP into 芽芽 */}
      <section className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6">
        <div className="flex items-center gap-2 mb-1">
          <SiteIcon name="trophy" size={18} className="text-[#9F6B3E]" />
          <h2 className="text-base font-black text-slate-800">芽芽里程碑</h2>
        </div>
        <p className="text-[11px] text-slate-500 mb-4">每位成功推薦的朋友都會幫芽芽長大，並解鎖以下成就</p>

        <ul className="space-y-3">
          {[
            { target: 1,  name: '第一位推薦者', desc: '+25 XP · 銀牌成就', tier: 'silver' },
            { target: 3,  name: '仙女推廣大使', desc: '+50 XP · 金牌成就', tier: 'gold' },
            { target: 10, name: '仙女 KOL',     desc: '+50 XP · 金牌成就', tier: 'gold' },
          ].map((m) => {
            const current = Math.min(data.referrals_success, m.target);
            const earned = current >= m.target;
            const pct = (current / m.target) * 100;
            return (
              <li key={m.target} className={`p-3 rounded-2xl border ${earned ? 'border-[#9F6B3E] bg-[#fdf7ef]' : 'border-[#e7d9cb] bg-white'}`}>
                <div className="flex items-baseline justify-between gap-2 mb-1.5">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className={`text-sm font-black ${earned ? 'text-[#9F6B3E]' : 'text-slate-700'} truncate`}>
                      {m.name}
                    </span>
                    {earned && <span className="text-[10px] font-black px-1.5 py-0.5 rounded-full bg-[#9F6B3E] text-white shrink-0">已達成</span>}
                  </div>
                  <span className="text-xs font-black text-slate-500 tabular-nums shrink-0">
                    {current}/{m.target}
                  </span>
                </div>
                <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                  <div
                    className={`h-full transition-all duration-500 ${earned ? 'bg-[#9F6B3E]' : 'bg-[#c0392b]'}`}
                    style={{ width: `${pct}%` }}
                  />
                </div>
                <p className="text-[10px] text-slate-500 mt-1.5">{m.desc}</p>
              </li>
            );
          })}
        </ul>

        <p className="text-[10px] text-slate-400 mt-3 leading-relaxed">
          每滿 100 XP 芽芽升 1 級。新成就會解鎖更多服裝與背景，到 <Link href="/account/mascot" className="underline text-[#9F6B3E]">芽芽之家</Link> 看看。
        </p>
      </section>

      {/* How it works */}
      <section className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6">
        <h2 className="text-base font-black text-slate-800 mb-4"><SiteIcon name="gift" size={16} className="inline" /> 運作方式</h2>
        <ol className="space-y-3">
          {[
            { step: '1', title: '分享邀請碼', desc: '複製上方邀請碼或完整連結，貼給朋友' },
            { step: '2', title: '朋友加入並下單', desc: '朋友用此碼註冊，完成首筆訂單後才算成功' },
            { step: '3', title: '雙方解鎖成就', desc: '你獲得「推薦者」成就，朋友獲得「被邀請」成就（增加等級經驗值）' },
          ].map((s) => (
            <li key={s.step} className="flex gap-3">
              <div className="w-7 h-7 rounded-full bg-[#9F6B3E] text-white font-black text-sm flex items-center justify-center shrink-0">
                {s.step}
              </div>
              <div>
                <div className="text-sm font-black text-slate-800">{s.title}</div>
                <div className="text-[11px] text-slate-500 mt-0.5">{s.desc}</div>
              </div>
            </li>
          ))}
        </ol>
      </section>

      {/* Share actions */}
      <section className="space-y-2">
        <button
          onClick={() => copy(link)}
          className="w-full px-5 py-3 rounded-2xl bg-[#fdf7ef] border border-[#e7d9cb] text-[#9F6B3E] font-black text-sm text-left flex items-center justify-between active:bg-[#f7eee3]"
        >
          <span className="truncate"><SiteIcon name="link" size={14} className="inline" /> {link}</span>
          <span className="text-xs shrink-0 ml-2">複製</span>
        </button>
        <div className="grid grid-cols-2 gap-2">
          <a
            href={`https://line.me/R/msg/text/?${encodeURIComponent(shareText)}`}
            target="_blank" rel="noopener"
            className="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-[#06C755] text-white font-black text-sm"
          >
            <SiteIcon name="chat" size={16} /> LINE 分享
          </a>
          <button
            onClick={() => copy(shareText)}
            className="flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm"
          >
            複製分享文案
          </button>
        </div>
      </section>

      <p className="text-[10px] text-slate-400 text-center leading-relaxed px-4">
        * 本站推薦獎勵為經驗值/成就，不含現金或點數回饋。違反誠信原則的推薦（如自我推薦、虛假訂單）將無法獲得獎勵。
      </p>
    </div>
  );
}

function StatCard({ emoji, label, value }: { emoji: string; label: string; value: string }) {
  return (
    <div className="bg-white rounded-2xl border border-[#e7d9cb] p-4 text-center">
      <div className="flex justify-center"><SiteIcon name={emoji} size={24} /></div>
      <div className="text-[10px] font-black text-slate-400 tracking-wider mt-1">{label}</div>
      <div className="text-base font-black text-slate-800 mt-0.5">{value}</div>
    </div>
  );
}
