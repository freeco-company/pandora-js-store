'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { Suspense, useEffect, useState } from 'react';
import LogoLoader from '@/components/LogoLoader';
import Mascot from '@/components/Mascot';
import ScrollReveal from '@/components/ScrollReveal';
import FloatingShapes from '@/components/FloatingShapes';
import { useAuth } from '@/components/AuthProvider';
import SiteIcon from '@/components/SiteIcon';
import OutfitIcon from '@/components/OutfitIcon';
import { getCustomerDashboard, type CustomerDashboard, API_URL, fetchApi } from '@/lib/api';
import { ACHIEVEMENT_CATALOG, stageFromStreak, TIER_GRADIENTS } from '@/lib/achievements';
import CodLineConfirmation from '@/components/CodLineConfirmation';

function OrderCompleteContent() {
  const searchParams = useSearchParams();
  const orderNumber = searchParams.get('order');
  const paymentMethod = searchParams.get('payment');
  const justBound = searchParams.get('bound') === '1';
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
            <div className="relative inline-flex w-24 h-24 bg-gradient-to-br from-[#9F6B3E] to-[#85572F] rounded-full items-center justify-center mb-5 shadow-xl shadow-[#9F6B3E]/30 celebrate-pop success-icon-wrap">
              {/* Multi-color, animated party popper */}
              <svg width="52" height="52" viewBox="0 0 40 40" fill="none" aria-hidden className="success-party-icon">
                {/* Cone */}
                <path d="M6 34L14 8L30 22Z" fill="#FFF3B0" />
                <path d="M6 34L14 8L30 22Z" fill="url(#cone-grad)" opacity="0.9" />
                <path d="M14 8L30 22" stroke="#FFE066" strokeWidth="1.5" strokeLinecap="round" opacity="0.8" />
                {/* Sparkle center */}
                <circle cx="17" cy="20" r="1.5" fill="#fff" opacity="0.9" />
                <defs>
                  <linearGradient id="cone-grad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stopColor="#FFE066" />
                    <stop offset="1" stopColor="#F5A623" />
                  </linearGradient>
                </defs>
              </svg>
              {/* Confetti particles bursting outward */}
              {[
                { x: -36, y: -28, c: '#E54B6E', d: 0.0 },
                { x: 38, y: -20, c: '#4A9ECD', d: 0.15 },
                { x: -44, y: 8, c: '#4A9D5F', d: 0.3 },
                { x: 42, y: 18, c: '#FFE066', d: 0.45 },
                { x: -24, y: 32, c: '#F27BA7', d: 0.6 },
                { x: 30, y: 34, c: '#9F6B3E', d: 0.2 },
                { x: -6, y: -40, c: '#7AC74F', d: 0.4 },
                { x: 10, y: 40, c: '#E8A93B', d: 0.55 },
              ].map((p, i) => (
                <span
                  key={i}
                  aria-hidden
                  className="success-confetti"
                  style={{
                    ['--dx' as string]: `${p.x}px`,
                    ['--dy' as string]: `${p.y}px`,
                    backgroundColor: p.c,
                    animationDelay: `${p.d}s`,
                  }}
                />
              ))}
            </div>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={150}>
            <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">
              {paymentMethod === 'cod' ? 'PENDING · 待您確認' : 'SUCCESS · 訂單成立'}
            </div>
            <h1 className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight">
              {paymentMethod === 'cod' ? '訂單已收到，請完成確認' : '感謝您的訂購！'}
            </h1>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={300}>
            {orderNumber && (
              <p className="text-base text-gray-700 mt-5">
                訂單編號：<span className="font-black text-[#9F6B3E] tracking-wide">{orderNumber}</span>
              </p>
            )}
            <p className="text-sm text-gray-500 mt-3 max-w-md mx-auto leading-relaxed">
              {paymentMethod === 'cod'
                ? '請依下方步驟在 LINE 上完成確認，我們才會安排出貨。48 小時未確認訂單將自動取消。'
                : '我們會盡快處理您的訂單，出貨後您會收到通知。'}
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

          /* Party popper icon — wiggles briefly then settles */
          @keyframes success-party-wiggle {
            0%   { transform: rotate(-8deg) scale(0.9); }
            30%  { transform: rotate(8deg) scale(1.1); }
            60%  { transform: rotate(-4deg) scale(1); }
            100% { transform: rotate(-8deg) scale(1); }
          }
          .success-party-icon {
            animation: success-party-wiggle 2.4s ease-in-out 0.6s infinite;
            transform-origin: 30% 70%;
          }

          /* Confetti bursting outward from center, fading as they fly */
          @keyframes success-confetti-fly {
            0%   { transform: translate(0, 0) scale(0) rotate(0); opacity: 0; }
            20%  { opacity: 1; }
            100% {
              transform: translate(var(--dx), var(--dy)) scale(1.1) rotate(720deg);
              opacity: 0;
            }
          }
          .success-confetti {
            position: absolute;
            left: 50%; top: 50%;
            width: 7px; height: 10px;
            border-radius: 2px;
            opacity: 0;
            pointer-events: none;
            animation: success-confetti-fly 2.2s cubic-bezier(0.16, 1, 0.3, 1) 0.4s infinite;
          }

          @media (prefers-reduced-motion: reduce) {
            .success-party-icon, .success-confetti { animation: none; }
          }
        `}</style>
      </section>

      <div className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 space-y-6 -mt-6 relative z-10">
        {/* COD + 超商取貨：強制 LINE 確認出貨 */}
        {paymentMethod === 'cod' && orderNumber && (
          <ScrollReveal variant="fade-up">
            <CodLineConfirmation orderNumber={orderNumber} justBound={justBound} />
          </ScrollReveal>
        )}

        {/* Bank transfer info — only shown for bank_transfer orders */}
        {paymentMethod === 'bank_transfer' && (
          <ScrollReveal variant="fade-up">
            <div className="bg-white rounded-3xl border-2 border-[#9F6B3E]/30 p-5 sm:p-7 shadow-sm">
              <div className="flex items-start gap-3 mb-4">
                <div className="w-10 h-10 rounded-full bg-[#fdf7ef] flex items-center justify-center shrink-0">
                  <SiteIcon name="shield" size={22} className="text-[#9F6B3E]" />
                </div>
                <div>
                  <h3 className="text-base font-black text-slate-800">請完成轉帳，我們立即為您出貨</h3>
                  <p className="text-xs text-slate-500 mt-0.5">
                    婕樂纖仙女館為 JEROSSE 婕樂纖授權經銷商，所有商品皆為原廠正品。
                  </p>
                </div>
              </div>

              <div className="bg-[#fdf7ef] rounded-2xl p-4 space-y-2.5 mb-4">
                <div className="flex justify-between text-sm">
                  <span className="text-slate-500">銀行</span>
                  <span className="font-black text-slate-800">富邦銀行（012）</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-500">帳號</span>
                  <span className="font-black text-[#9F6B3E] tracking-wider">82110000082812</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-slate-500">戶名</span>
                  <span className="font-black text-slate-800">法芮可有限公司</span>
                </div>
                <div className="flex justify-between text-sm border-t border-[#e7d9cb] pt-2.5">
                  <span className="text-slate-500">應付金額</span>
                  <span className="font-black text-[#9F6B3E] text-lg">{searchParams.get('total') && !isNaN(Number(searchParams.get('total'))) ? `NT$${Number(searchParams.get('total')).toLocaleString()}` : '請參考訂單金額'}</span>
                </div>
              </div>

              <button
                onClick={() => {
                  navigator.clipboard.writeText('82110000082812');
                  alert('已複製帳號！');
                }}
                className="w-full py-2.5 rounded-full border-2 border-[#9F6B3E] text-[#9F6B3E] text-sm font-black hover:bg-[#fdf7ef] transition-colors mb-3"
              >
                複製帳號
              </button>

              <div className="flex items-start gap-2 text-[11px] text-slate-500 leading-relaxed">
                <SiteIcon name="check-circle" size={14} className="text-green-500 shrink-0 mt-0.5" />
                <p>轉帳完成後，我們會在 1 個工作天內確認款項並安排出貨。如有任何疑問，歡迎透過 <a href="https://lin.ee/62wj7qa" target="_blank" rel="noopener" className="text-[#9F6B3E] font-bold underline">LINE 客服</a> 聯繫我們。</p>
              </div>
            </div>
          </ScrollReveal>
        )}

        {/* Primary CTA */}
        <ScrollReveal variant="fade-up">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Link
              href="/products"
              className="flex items-center justify-center px-6 py-3.5 bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/30 hover:shadow-xl transition-all min-h-[52px]"
            >
              <SiteIcon name="shopping-bag" size={20} /> 繼續選購
            </Link>
            <Link
              href="/account"
              className="flex items-center justify-center px-6 py-3.5 bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] font-black rounded-full hover:bg-[#fdf7ef] transition-all min-h-[52px]"
            >
              <SiteIcon name="sprout" size={20} /> 查看我的成就
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
                    <SiteIcon
                      name={nextSpendMilestone === 1000 ? 'money-bag' : nextSpendMilestone === 5000 ? 'diamond' : 'crown'}
                      size={28}
                      className="text-[#9F6B3E]"
                    />
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
                    <h3 className="text-sm font-black text-slate-800 flex items-center gap-1.5"><SiteIcon name="target" size={16} /> 接下來可能解鎖</h3>
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
                        <div className="text-2xl mb-1"><OutfitIcon name={def.emoji} size={28} /></div>
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
              <div className="text-3xl mb-2"><SiteIcon name="sprout" size={32} className="text-[#4A9D5F]" /></div>
              <h3 className="text-base font-black text-slate-800">登入後可解鎖成就系統</h3>
              <p className="text-xs text-slate-500 mt-1 mb-4">本次訂單也會計入成就進度喔！</p>
              <Link
                href="/account"
                className="inline-flex items-center px-5 py-2.5 bg-[#9F6B3E] text-white text-sm font-black rounded-full hover:bg-[#85572F] transition-colors min-h-[44px]"
              >
                <SiteIcon name="sprout" size={18} /> 登入解鎖
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
