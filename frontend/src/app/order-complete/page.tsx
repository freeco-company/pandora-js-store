'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useEffect, useState } from 'react';
import LogoLoader from '@/components/LogoLoader';
import Mascot from '@/components/Mascot';
import ScrollReveal from '@/components/ScrollReveal';
import FloatingShapes from '@/components/FloatingShapes';
import { useAuth } from '@/components/AuthProvider';
import { getCustomerDashboard, type CustomerDashboard } from '@/lib/api';
import { ACHIEVEMENT_CATALOG, stageFromStreak, TIER_GRADIENTS } from '@/lib/achievements';

function OrderCompleteContent() {
  const searchParams = useSearchParams();
  const orderNumber = searchParams.get('order');
  const { token, isLoggedIn } = useAuth();
  const [data, setData] = useState<CustomerDashboard | null>(null);

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    getCustomerDashboard(token).then(setData).catch(() => {});
  }, [token, isLoggedIn]);

  // Compute next milestone progress based on spending
  const spent = data?.customer.total_spent || 0;
  const nextSpendMilestone = spent < 1000 ? 1000 : spent < 5000 ? 5000 : spent < 10000 ? 10000 : 0;
  const prevSpendMilestone = spent < 1000 ? 0 : spent < 5000 ? 1000 : spent < 10000 ? 5000 : 10000;
  const spendProgress = nextSpendMilestone
    ? Math.min(100, ((spent - prevSpendMilestone) / (nextSpendMilestone - prevSpendMilestone)) * 100)
    : 100;

  // Pick 3 closest unearned achievements
  const earnedSet = new Set(data?.achievements.earned.map((e) => e.code) || []);
  const unearned = Object.values(ACHIEVEMENT_CATALOG)
    .filter((a) => !earnedSet.has(a.code))
    .slice(0, 3);

  return (
    <div className="relative">
      {/* Hero — confetti-style celebration */}
      <section
        className="relative overflow-hidden"
        style={{
          background:
            'radial-gradient(ellipse at 20% 30%, #f7c79a22 0%, transparent 50%),' +
            'radial-gradient(ellipse at 80% 70%, #e7a77e22 0%, transparent 50%),' +
            'linear-gradient(135deg, #e7d9cb 0%, #efe2d1 50%, #e7d9cb 100%)',
        }}
      >
        <FloatingShapes />
        <div className="relative max-w-3xl mx-auto px-5 sm:px-6 lg:px-8 py-14 sm:py-20 text-center">
          <ScrollReveal variant="zoom-in">
            <div className="inline-flex w-20 h-20 bg-gradient-to-br from-[#9F6B3E] to-[#85572F] rounded-full items-center justify-center text-4xl mb-5 shadow-xl shadow-[#9F6B3E]/30 celebrate-pop">
              🎉
            </div>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={150}>
            <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">SUCCESS · 訂單成立</div>
            <h1 className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight">感謝您的訂購！</h1>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={300}>
            {orderNumber && (
              <p className="text-base text-gray-700 mt-5">
                訂單編號：<span className="font-black text-[#9F6B3E] tracking-wide">{orderNumber}</span>
              </p>
            )}
            <p className="text-sm text-gray-500 mt-3 max-w-md mx-auto leading-relaxed">
              我們會盡快處理您的訂單，出貨後您會收到通知。
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>

        <style>{`
          @keyframes celebrate-pop {
            0% { transform: scale(0) rotate(-15deg); opacity: 0; }
            60% { transform: scale(1.2) rotate(10deg); opacity: 1; }
            100% { transform: scale(1) rotate(0); opacity: 1; }
          }
          .celebrate-pop { animation: celebrate-pop 0.7s cubic-bezier(0.2, 0.9, 0.3, 1.2) forwards; }
        `}</style>
      </section>

      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 space-y-6 -mt-6 relative z-10">
        {/* Primary CTA */}
        <ScrollReveal variant="fade-up">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Link
              href="/products"
              className="flex items-center justify-center px-6 py-3.5 bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/30 hover:shadow-xl transition-all min-h-[52px]"
            >
              🛍️ 繼續選購
            </Link>
            <Link
              href="/account"
              className="flex items-center justify-center px-6 py-3.5 bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] font-black rounded-full hover:bg-[#fdf7ef] transition-all min-h-[52px]"
            >
              🌱 查看我的成就
            </Link>
          </div>
        </ScrollReveal>

        {/* Gamification section — only shown if logged in with dashboard */}
        {isLoggedIn && data && (
          <>
            {/* Mascot + streak greeting */}
            <ScrollReveal variant="fade-up" delay={100}>
              <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-5 sm:p-6 border border-[#e7d9cb] flex items-center gap-4">
                <Mascot
                  stage={stageFromStreak(data.customer.streak_days)}
                  mood="excited"
                  size={80}
                  outfit={data.customer.current_outfit}
                  backdrop={data.customer.current_backdrop}
                />
                <div className="flex-1 min-w-0">
                  <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-1">芽芽為你加油</div>
                  <h3 className="text-base font-black text-slate-800">
                    累積已消費 NT${data.customer.total_spent.toLocaleString()}
                  </h3>
                  <p className="text-xs text-slate-500 mt-1">
                    已累積 {data.customer.total_orders} 筆訂單 · {data.achievements.earned.length} 個成就
                  </p>
                </div>
              </div>
            </ScrollReveal>

            {/* Spending milestone progress */}
            {nextSpendMilestone > 0 && (
              <ScrollReveal variant="fade-up" delay={150}>
                <div className="bg-white rounded-3xl p-5 sm:p-6 border border-[#e7d9cb] shadow-sm">
                  <div className="flex items-center justify-between mb-3">
                    <div>
                      <div className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E]">NEXT MILESTONE</div>
                      <div className="text-sm font-black text-slate-800 mt-0.5">
                        距離下個里程碑 <span className="text-[#9F6B3E]">NT${(nextSpendMilestone - spent).toLocaleString()}</span>
                      </div>
                    </div>
                    <span className="text-2xl">
                      {nextSpendMilestone === 1000 ? '💰' : nextSpendMilestone === 5000 ? '💎' : '👑'}
                    </span>
                  </div>
                  <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div
                      className="h-full bg-gradient-to-r from-[#e7d9cb] to-[#9F6B3E] rounded-full transition-all duration-700"
                      style={{ width: `${spendProgress}%` }}
                    />
                  </div>
                  <div className="flex justify-between text-[10px] font-black text-slate-400 mt-1.5">
                    <span>NT${prevSpendMilestone.toLocaleString()}</span>
                    <span>NT${nextSpendMilestone.toLocaleString()}</span>
                  </div>
                </div>
              </ScrollReveal>
            )}

            {/* Upcoming achievements */}
            {unearned.length > 0 && (
              <ScrollReveal variant="fade-up" delay={200}>
                <div className="bg-white rounded-3xl p-5 sm:p-6 border border-[#e7d9cb] shadow-sm">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-sm font-black text-slate-800 flex items-center gap-1.5">🎯 接下來可能解鎖</h3>
                    <Link href="/account" className="text-[10px] font-black text-[#9F6B3E]">
                      全部成就 →
                    </Link>
                  </div>
                  <div className="grid grid-cols-3 gap-3">
                    {unearned.map((def) => (
                      <div
                        key={def.code}
                        className={`aspect-square rounded-2xl p-2 flex flex-col items-center justify-center text-center bg-gradient-to-br ${TIER_GRADIENTS[def.tier]} opacity-75 ring-1 ring-white/40`}
                      >
                        <div className="text-2xl mb-1">{def.emoji}</div>
                        <div className="text-[10px] font-black leading-tight text-slate-800">
                          {def.name}
                        </div>
                        <div className="text-[8px] font-bold text-slate-600 mt-0.5 line-clamp-2 leading-tight">
                          {def.description}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              </ScrollReveal>
            )}
          </>
        )}

        {/* Guest: encourage login */}
        {!isLoggedIn && (
          <ScrollReveal variant="fade-up" delay={200}>
            <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-5 sm:p-6 border border-[#e7d9cb] text-center">
              <div className="text-3xl mb-2">🌱</div>
              <h3 className="text-base font-black text-slate-800">登入後可解鎖成就系統</h3>
              <p className="text-xs text-slate-500 mt-1 mb-4">本次訂單也會計入成就進度喔！</p>
              <Link
                href="/account"
                className="inline-flex items-center px-5 py-2.5 bg-[#9F6B3E] text-white text-sm font-black rounded-full hover:bg-[#85572F] transition-colors min-h-[44px]"
              >
                🌱 登入解鎖
              </Link>
            </div>
          </ScrollReveal>
        )}
      </div>
    </div>
  );
}

export default function OrderCompletePage() {
  return (
    <Suspense fallback={<div className="flex items-center justify-center py-24 min-h-[60vh]"><LogoLoader size={80} /></div>}>
      <OrderCompleteContent />
    </Suspense>
  );
}
