'use client';

/**
 * /account/recap — personal monthly stats ("月度回顧").
 * Shows the current month's purchase activity + achievement progress.
 * All data comes from /api/customer/dashboard + /api/customer/orders.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import LogoLoader from '@/components/LogoLoader';
import {
  getCustomerDashboard,
  type CustomerDashboard,
} from '@/lib/api';
import { ACHIEVEMENT_CATALOG, TIER_GRADIENTS } from '@/lib/achievements';
import { formatPrice } from '@/lib/format';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface OrderSummary {
  order_number: string;
  total: number;
  created_at: string;
  status: string;
}

export default function RecapPage() {
  const router = useRouter();
  const { token, isLoggedIn, loading: authLoading } = useAuth();
  const [data, setData] = useState<CustomerDashboard | null>(null);
  const [orders, setOrders] = useState<OrderSummary[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (authLoading) return;
    if (!isLoggedIn || !token) {
      router.replace('/account');
      return;
    }
    Promise.all([
      getCustomerDashboard(token),
      fetch(`${API_URL}/customer/orders?per_page=100`, {
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
      }).then((r) => (r.ok ? r.json() : { data: [] })),
    ])
      .then(([d, o]) => {
        setData(d);
        setOrders(o.data ?? []);
      })
      .finally(() => setLoading(false));
  }, [token, isLoggedIn, authLoading, router]);

  if (loading) return <div className="py-24 flex justify-center"><LogoLoader size={72} /></div>;
  if (!data) return null;

  // This-month vs last-month spend
  const now = new Date();
  const thisMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const lastMonthDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const lastMonth = `${lastMonthDate.getFullYear()}-${String(lastMonthDate.getMonth() + 1).padStart(2, '0')}`;

  const byMonth = orders.reduce<Record<string, { count: number; total: number }>>((acc, o) => {
    const key = o.created_at.slice(0, 7);
    if (!acc[key]) acc[key] = { count: 0, total: 0 };
    if (o.status !== 'cancelled' && o.status !== 'refunded') {
      acc[key].count++;
      acc[key].total += Number(o.total);
    }
    return acc;
  }, {});

  const thisMonthStats = byMonth[thisMonth] ?? { count: 0, total: 0 };
  const lastMonthStats = byMonth[lastMonth] ?? { count: 0, total: 0 };
  const deltaPct = lastMonthStats.total > 0
    ? Math.round(((thisMonthStats.total - lastMonthStats.total) / lastMonthStats.total) * 100)
    : null;

  // Next milestone
  const nextAchievement = Object.values(ACHIEVEMENT_CATALOG).find((a) => {
    const earned = data.achievements.earned.some((e) => e.code === a.code);
    return !earned;
  });
  const earnedCount = data.achievements.earned.length;
  const totalCount = Object.keys(ACHIEVEMENT_CATALOG).length;

  const monthName = `${now.getFullYear()} 年 ${now.getMonth() + 1} 月`;

  return (
    <div className="max-w-2xl mx-auto p-4 sm:p-6 pb-24 space-y-5">
      <div className="flex items-center gap-2">
        <Link href="/account" className="text-[#9F6B3E] text-sm font-black">← 我的仙女館</Link>
      </div>

      {/* Hero */}
      <div className="bg-gradient-to-br from-[#9F6B3E] via-[#8d5f36] to-[#6b4424] text-white rounded-3xl p-6 sm:p-8 relative overflow-hidden">
        <span className="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#fcd561]/10 blur-2xl" />
        <div className="relative">
          <div className="text-[10px] font-black tracking-[0.3em] text-[#fcd561]">MONTHLY RECAP</div>
          <h1 className="text-2xl sm:text-3xl font-black mt-1">{monthName}回顧</h1>
          <div className="mt-4 flex items-baseline gap-2">
            <span className="text-4xl sm:text-5xl font-black">{formatPrice(thisMonthStats.total)}</span>
            <span className="text-xs text-white/70">本月消費</span>
          </div>
          <div className="mt-1 flex items-center gap-3 text-[11px] text-white/80">
            <span>🛒 {thisMonthStats.count} 筆訂單</span>
            {deltaPct !== null && (
              <span className={deltaPct >= 0 ? 'text-[#fcd561]' : 'text-pink-200'}>
                {deltaPct >= 0 ? '▲' : '▼'} 比上月 {Math.abs(deltaPct)}%
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Lifetime card grid */}
      <section className="grid grid-cols-3 gap-3">
        <StatCard emoji="🔥" label="連續造訪" value={`${data.customer.streak_days} 天`} />
        <StatCard emoji="🛍️" label="累積訂單" value={`${data.customer.total_orders} 筆`} />
        <StatCard emoji="💰" label="累積消費" value={`NT$${data.customer.total_spent.toLocaleString()}`} />
      </section>

      {/* Achievement progress */}
      <section className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6">
        <div className="flex items-center justify-between mb-3">
          <div>
            <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">ACHIEVEMENTS</div>
            <h2 className="text-lg font-black text-slate-800 mt-0.5">🏆 成就進度</h2>
          </div>
          <span className="text-sm font-black text-[#9F6B3E]">{earnedCount} / {totalCount}</span>
        </div>
        <div className="h-2 rounded-full bg-slate-100 overflow-hidden mb-3">
          <div
            className="h-full bg-gradient-to-r from-[#e7d9cb] to-[#9F6B3E] rounded-full transition-all duration-500"
            style={{ width: `${(earnedCount / totalCount) * 100}%` }}
          />
        </div>
        {nextAchievement && (
          <div className={`bg-gradient-to-br ${TIER_GRADIENTS[nextAchievement.tier]} rounded-2xl p-4 flex items-center gap-3 opacity-80`}>
            <div className="text-4xl">{nextAchievement.emoji}</div>
            <div className="flex-1">
              <div className="text-[10px] font-black text-[#5a4234]">下一個目標</div>
              <div className="text-sm font-black text-[#3d2e22]">{nextAchievement.name}</div>
              <div className="text-[11px] text-[#5a4234]/80 mt-0.5">{nextAchievement.description}</div>
            </div>
          </div>
        )}
      </section>

      {/* Recent orders */}
      {orders.length > 0 && (
        <section className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-6">
          <div className="flex items-center justify-between mb-3">
            <h2 className="text-lg font-black text-slate-800">📦 最近訂單</h2>
            <Link href="/order-lookup" className="text-xs font-black text-[#9F6B3E]">全部 →</Link>
          </div>
          <div className="space-y-2">
            {orders.slice(0, 5).map((o) => (
              <div key={o.order_number} className="flex items-center justify-between py-2 border-b border-slate-100 last:border-0">
                <div>
                  <div className="text-[11px] text-slate-400 font-bold">#{o.order_number}</div>
                  <div className="text-[11px] text-slate-500">{new Date(o.created_at).toLocaleDateString('zh-TW')}</div>
                </div>
                <div className="text-sm font-black text-[#9F6B3E]">{formatPrice(o.total)}</div>
              </div>
            ))}
          </div>
        </section>
      )}

      {/* Share CTA */}
      <section className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-5 sm:p-6 border border-[#e7d9cb] text-center">
        <div className="text-3xl">🌸</div>
        <h3 className="text-base font-black text-slate-800 mt-2">享受仙女生活？</h3>
        <p className="text-[11px] text-slate-500 mt-1 mb-4">邀請朋友一起，雙方都能獲得成就 + 經驗值</p>
        <Link
          href="/account/referral"
          className="inline-flex items-center gap-2 px-5 py-2.5 bg-[#9F6B3E] text-white font-black text-sm rounded-full hover:bg-[#85572F] transition-colors"
        >
          分享我的邀請碼 →
        </Link>
      </section>
    </div>
  );
}

function StatCard({ emoji, label, value }: { emoji: string; label: string; value: string }) {
  return (
    <div className="bg-white rounded-2xl border border-[#e7d9cb] p-4 text-center">
      <div className="text-2xl">{emoji}</div>
      <div className="text-[10px] font-black text-slate-400 tracking-wider mt-1">{label}</div>
      <div className="text-sm sm:text-base font-black text-slate-800 mt-0.5">{value}</div>
    </div>
  );
}
