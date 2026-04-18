'use client';

import Image from 'next/image';
import { useState } from 'react';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';

type Tab = 'self' | 'business';

export default function JoinPage() {
  const [tab, setTab] = useState<Tab>('self');

  return (
    <div className="relative">
      {/* Hero */}
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
        <div className="relative max-w-4xl mx-auto px-5 sm:px-8 lg:px-12 py-16 sm:py-24 text-center">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-4 shadow-sm">
              <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
              <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">JOIN FP · 加入 FP</span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text="加入婕樂纖仙女館"
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-xl mx-auto leading-relaxed">
              只要 <strong className="text-[#9F6B3E]">NT$200 工本費</strong>，就能成為加盟會員。
              <br />
              選擇適合你的方案，全館商品直接用<strong className="text-[#9F6B3E]">成本價</strong>購買！
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>
      </section>

      {/* Tabs */}
      <div className="max-w-4xl mx-auto px-5 sm:px-8 lg:px-12 py-8 sm:py-12">
        <div className="grid grid-cols-2 gap-2 bg-[#fdf7ef] p-1.5 rounded-full max-w-xl mx-auto mb-10">
          <button
            onClick={() => setTab('self')}
            className={`py-3 px-4 rounded-full text-sm font-black transition-all ${
              tab === 'self'
                ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md'
                : 'text-[#7a5836] hover:bg-white/50'
            }`}
          >
            <IconSprout /> 自用加盟
          </button>
          <button
            onClick={() => setTab('business')}
            className={`py-3 px-4 rounded-full text-sm font-black transition-all ${
              tab === 'business'
                ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md'
                : 'text-[#7a5836] hover:bg-white/50'
            }`}
          >
            <IconDiamond /> 創業加盟
          </button>
        </div>

        {tab === 'self' ? <SelfUsePanel /> : <BusinessPanel />}
      </div>
    </div>
  );
}

/* ─── Self-use Panel ───────────────────────────────────────── */

function SelfUsePanel() {
  return (
    <div className="space-y-8">
      {/* Intro */}
      <ScrollReveal variant="fade-up">
        <div className="text-center mb-2">
          <p className="text-sm sm:text-base text-gray-600 leading-relaxed max-w-2xl mx-auto">
            自用加盟只要 <strong className="text-[#9F6B3E]">NT$200 工本費</strong>，
            全館商品隨你挑、湊滿 NT$6,600 或 NT$19,600 即可，<br />首次就用<strong className="text-[#9F6B3E]">成本價</strong>帶走。
            <br className="hidden sm:inline" />
            有活動時，活動贈品照拿、商品一樣成本價！
          </p>
        </div>
      </ScrollReveal>

      {/* Two pricing cards side by side */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
        {/* 6600 Plan */}
        <ScrollReveal variant="fade-up" delay={100}>
          <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-8 border border-[#e7d9cb] h-full flex flex-col">
            <div className="flex items-center gap-2 mb-3">
              <IconCoin className="text-[#D4A053]" />
              <span className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E]">PLAN A</span>
            </div>
            <h3 className="text-2xl sm:text-3xl font-black text-gray-900 mb-1">
              NT$6,600
            </h3>
            <p className="text-xs text-gray-500 mb-4">+ NT$200 工本費</p>
            <p className="text-sm text-gray-700 leading-relaxed mb-5 flex-1">
              任挑商品 NT$6,600，首次就用成本價購買。適合想試試、開啟保養之路的你。
            </p>
            <div className="pt-3 border-t border-[#e7d9cb]/60">
              <p className="text-xs text-[#9F6B3E] font-bold">加盟成本價</p>
            </div>
          </div>
        </ScrollReveal>

        {/* 19600 Plan */}
        <ScrollReveal variant="fade-up" delay={200}>
          <div className="bg-gradient-to-br from-[#fef6e4] via-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-8 border-2 border-[#9F6B3E]/40 h-full flex flex-col relative overflow-hidden">
            <span className="absolute top-3 right-3 bg-[#9F6B3E] text-white text-[10px] font-black px-2.5 py-1 rounded-full">
              更划算
            </span>
            <div className="flex items-center gap-2 mb-3">
              <IconCrown className="text-[#D4A053]" />
              <span className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E]">PLAN B</span>
            </div>
            <h3 className="text-2xl sm:text-3xl font-black text-gray-900 mb-1">
              NT$19,600
            </h3>
            <p className="text-xs text-gray-500 mb-4">+ NT$200 工本費</p>
            <p className="text-sm text-gray-700 leading-relaxed mb-5 flex-1">
              任挑商品 NT$19,600，<br />首次就用成本價購買，而且成本價更便宜！適合長期使用、想一次備齊的你。
            </p>
            <div className="pt-3 border-t border-[#9F6B3E]/20">
              <p className="text-xs text-[#9F6B3E] font-bold">更優惠的成本價</p>
            </div>
          </div>
        </ScrollReveal>
      </div>

      {/* Shared benefits */}
      <ScrollReveal variant="fade-up" delay={300}>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {[
            { icon: <IconNoContract className="text-[#E8864B]" />, t: '不用綁約', d: '沒有合約束縛，自由自在' },
            { icon: <IconInfinity className="text-[#4A9D5F]" />, t: '永久有效', d: '一次加入，終身享成本價' },
            { icon: <IconZeroStock className="text-[#5B8EC9]" />, t: '零囤貨', d: '不用備貨，直接成本價購買' },
          ].map((b, i) => (
            <div key={i} className="bg-white rounded-2xl p-4 border border-[#e7d9cb] text-center">
              <div className="mb-2 flex justify-center">{b.icon}</div>
              <div className="font-black text-gray-900 text-sm mb-1">{b.t}</div>
              <div className="text-xs text-gray-500">{b.d}</div>
            </div>
          ))}
        </div>
      </ScrollReveal>

      {/* Bonus callout */}
      <ScrollReveal variant="fade-up" delay={400}>
        <div className="bg-[#fdf7ef] rounded-2xl p-5 border border-[#e7d9cb] flex items-center gap-3">
          <IconGift className="text-[#E05B8C] shrink-0" />
          <p className="text-sm text-gray-700 leading-relaxed">
            <strong className="text-[#9F6B3E]">遇到活動更划算！</strong>
            活動贈品照拿，商品同時享有成本價，等於雙重優惠。
          </p>
        </div>
      </ScrollReveal>

      {/* Process image */}
      <ProcessSection />

      {/* CTA */}
      <CTASection />
    </div>
  );
}

/* ─── Business Panel ───────────────────────────────────────── */

function BusinessPanel() {
  return (
    <div className="space-y-8">
      <ScrollReveal variant="fade-up">
        <div className="bg-gradient-to-br from-[#fef6e4] via-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-10 border border-[#9F6B3E]/30">
          <div className="flex items-center gap-3 mb-4">
            <IconDiamondLarge className="text-[#9F6B3E]" />
            <div>
              <div className="text-[10px] font-black tracking-[0.3em] text-[#7a5836]">BUSINESS · 創業之路</div>
              <h2 className="text-2xl sm:text-3xl font-black text-gray-900 mt-1">創業加盟 · 一起打造事業</h2>
            </div>
          </div>
          <p className="text-sm sm:text-base text-gray-700 leading-relaxed mb-6">
            不只是買得便宜，更是<strong className="text-[#9F6B3E]">建立屬於自己的事業</strong>。
            <br className="hidden sm:inline" />
            完整培訓系統 + 團隊支持 + 階梯式獎金，從零開始陪你成為仙女團隊核心。
          </p>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {[
              { icon: <IconBook className="text-[#5B8EC9]" />, t: '系統化培訓', d: '產品知識、社群經營、客戶服務完整課程' },
              { icon: <IconPhone className="text-[#9F6B3E]" />, t: '現成素材庫', d: '貼文模板、直播腳本、圖文素材一應俱全' },
              { icon: <IconMoneyBag className="text-[#D4A053]" />, t: '制度簡單', d: '單純賺價差，沒有複雜的獎金計算' },
              { icon: <IconFlower className="text-[#E05B8C]" />, t: '團隊陪伴', d: '不是一個人單打獨鬥，有夥伴一起成長' },
              { icon: <IconTarget className="text-[#E8864B]" />, t: '個人品牌打造', d: '協助你建立 IG / 直播 / 個人定位' },
              { icon: <IconStethoscope className="text-[#4A9D5F]" />, t: '零風險創業', d: '不用囤貨、不用綁約，隨時可以開始' },
            ].map((b, i) => (
              <div key={i} className="bg-white rounded-2xl p-4 border border-[#e7d9cb]">
                <div className="mb-2">{b.icon}</div>
                <div className="font-black text-gray-900 text-sm mb-1">{b.t}</div>
                <div className="text-xs text-gray-500 leading-relaxed">{b.d}</div>
              </div>
            ))}
          </div>
        </div>
      </ScrollReveal>

      {/* Extra steps before process */}
      <ScrollReveal variant="fade-up">
        <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e7d9cb]">
          <h3 className="text-lg font-black text-gray-900 mb-4">
            <IconClipboard className="text-[#9F6B3E]" /> 創業加盟流程
          </h3>
          <ol className="space-y-3 mb-6">
            {[
              '私訊 LINE / IG 說明你的想法',
              '加盟諮詢（了解你的創業目標，解說團隊資源與培訓方式）',
            ].map((step, i) => (
              <li key={i} className="flex items-start gap-3">
                <span className="w-7 h-7 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm flex items-center justify-center shrink-0">
                  {i + 1}
                </span>
                <span className="text-sm text-gray-800 pt-1">{step}</span>
              </li>
            ))}
          </ol>
          <p className="text-sm text-gray-500 mb-2">確認加盟後，依照以下流程完成註冊：</p>
        </div>
      </ScrollReveal>

      {/* Process image (same as self-use) */}
      <ProcessSection />

      {/* Founder quote */}
      <ScrollReveal variant="fade-up">
        <div className="bg-[#1a1410] text-[#f7eee3] rounded-3xl p-6 sm:p-10 relative overflow-hidden">
          <span className="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#9F6B3E]/30 blur-3xl" />
          <div className="relative">
            <div className="text-[10px] font-black tracking-[0.3em] text-[#e7d9cb]/60 mb-2">FOUNDER SAYS</div>
            <p className="text-base sm:text-lg leading-relaxed font-bold mb-4">
              &ldquo;我不會答應你一夜致富，但我會陪你打造能放 10 年的事業。&rdquo;
            </p>
            <div className="text-sm text-[#9F6B3E] font-black">&mdash; 朵朵 · Co-Founder</div>
          </div>
        </div>
      </ScrollReveal>

      {/* CTA */}
      <CTASection />
    </div>
  );
}

/* ─── Shared: Process Image ────────────────────────────────── */

function ProcessSection() {
  return (
    <ScrollReveal variant="fade-up">
      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e7d9cb]">
        <h3 className="text-lg font-black text-gray-900 mb-5 text-center">
          <IconClipboard className="text-[#9F6B3E]" /> 加盟步驟教學流程
        </h3>
        <div className="relative w-full max-w-2xl mx-auto">
          <Image
            src="/images/join-process.jpg"
            alt="加盟步驟教學流程圖：LINE群組註冊 → 系統註冊考核 → 新人訓3.5小時 → 完成加盟"
            width={800}
            height={1000}
            className="w-full h-auto rounded-2xl"
            priority
          />
        </div>
      </div>
    </ScrollReveal>
  );
}

/* ─── Shared: CTA ──────────────────────────────────────────── */

function CTASection() {
  return (
    <div className="text-center pt-4">
      <a
        href="https://lin.ee/62wj7qa"
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-2 px-10 py-4 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg hover:bg-[#85572F] transition-colors min-h-[52px]"
      >
        <IconChat /> LINE 諮詢
      </a>
      <p className="text-xs text-gray-500 mt-3">
        或先追蹤 IG{' '}
        <a href="https://www.instagram.com/pandorasdo/" target="_blank" rel="noopener noreferrer" className="font-black text-[#9F6B3E] underline">
          @pandorasdo
        </a>
      </p>
    </div>
  );
}

/* ─── Colorful SVG Icons (inline, no SiteIcon dependency) ──── */

function IconSprout() {
  return (
    <svg className="inline-block shrink-0 -mt-0.5" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden>
      <path d="M10 18V10" stroke="#4A9D5F" strokeWidth="1.5" strokeLinecap="round" />
      <path d="M10 12C10 9 13 7 16 7c0 3-2 5-6 5z" fill="#4A9D5F" />
      <path d="M10 14C10 11 7 9 4 9c0 3 2 5 6 5z" fill="#6BC07E" />
    </svg>
  );
}

function IconDiamond() {
  return (
    <svg className="inline-block shrink-0 -mt-0.5" width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden>
      <path d="M10 2L17 8L10 18L3 8Z" fill="#5BB8E8" />
      <path d="M3 8h14" stroke="#fff" strokeWidth="0.5" opacity="0.5" />
      <path d="M7 2l-4 6M13 2l4 6M10 2v6" stroke="#fff" strokeWidth="0.5" opacity="0.4" />
    </svg>
  );
}

function IconCoin({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <circle cx="12" cy="12" r="9" fill="#F5D680" stroke="#D4A053" strokeWidth="1.5" />
      <text x="12" y="16" textAnchor="middle" fill="#9F6B3E" fontSize="10" fontWeight="bold">$</text>
    </svg>
  );
}

function IconCrown({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M4 17l2.5-9 3.5 4.5L12 6l2 6.5L17.5 8 20 17z" fill="#F5D680" stroke="#D4A053" strokeWidth="1" />
      <rect x="4" y="17" width="16" height="3" rx="1" fill="#D4A053" />
    </svg>
  );
}

function IconNoContract({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <rect x="6" y="3" width="12" height="18" rx="2" stroke="#E8864B" strokeWidth="1.5" fill="#FDE8D8" />
      <line x1="9" y1="8" x2="15" y2="8" stroke="#E8864B" strokeWidth="1" strokeLinecap="round" />
      <line x1="9" y1="11" x2="13" y2="11" stroke="#E8864B" strokeWidth="1" strokeLinecap="round" />
      <line x1="4" y1="4" x2="20" y2="20" stroke="#E05B5B" strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

function IconInfinity({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M7 12a3.5 3.5 0 1 1 0-.01M17 12a3.5 3.5 0 1 1 0-.01" stroke="#4A9D5F" strokeWidth="2" strokeLinecap="round" fill="none" />
      <path d="M10.5 9.5c1.5-1.5 3-1.5 4 0M10.5 14.5c1.5 1.5 3 1.5 4 0" stroke="#4A9D5F" strokeWidth="2" strokeLinecap="round" fill="none" />
    </svg>
  );
}

function IconZeroStock({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M4 8l8-4.5L20 8v8l-8 4.5L4 16V8z" stroke="#5B8EC9" strokeWidth="1.5" fill="#DDEAF7" />
      <path d="M4 8l8 4.5 8-4.5M12 12.5V21" stroke="#5B8EC9" strokeWidth="1.2" />
      <circle cx="12" cy="12" r="2" fill="#fff" stroke="#5B8EC9" strokeWidth="1" />
      <text x="12" y="14" textAnchor="middle" fill="#5B8EC9" fontSize="5" fontWeight="bold">0</text>
    </svg>
  );
}

function IconGift({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden>
      <rect x="4" y="10" width="16" height="3" rx="1" fill="#FFD6E0" stroke="#E05B8C" strokeWidth="1" />
      <rect x="5" y="13" width="14" height="7" rx="1" fill="#FFE8EE" stroke="#E05B8C" strokeWidth="1" />
      <line x1="12" y1="10" x2="12" y2="20" stroke="#E05B8C" strokeWidth="1" />
      <path d="M12 10C12 10 10 6 8 7s0 3 4 3" stroke="#E05B8C" strokeWidth="1" fill="none" />
      <path d="M12 10C12 10 14 6 16 7s0 3-4 3" stroke="#E05B8C" strokeWidth="1" fill="none" />
    </svg>
  );
}

function IconDiamondLarge({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M12 2L21 9L12 22L3 9Z" fill="#B8DCFF" stroke="#5B8EC9" strokeWidth="0.8" />
      <path d="M3 9h18" stroke="#5B8EC9" strokeWidth="0.5" opacity="0.5" />
      <path d="M8 2l-5 7M16 2l5 7M12 2v7" stroke="#5B8EC9" strokeWidth="0.5" opacity="0.4" />
    </svg>
  );
}

function IconBook({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M4 19.5A1.5 1.5 0 015.5 18H20V3H5.5A1.5 1.5 0 004 4.5v15z" fill="#DDEAF7" stroke="#5B8EC9" strokeWidth="1.2" />
      <path d="M4 19.5A1.5 1.5 0 015.5 18H20" stroke="#5B8EC9" strokeWidth="1.2" fill="none" />
      <line x1="8" y1="7" x2="16" y2="7" stroke="#5B8EC9" strokeWidth="0.8" strokeLinecap="round" />
      <line x1="8" y1="10" x2="13" y2="10" stroke="#5B8EC9" strokeWidth="0.8" strokeLinecap="round" />
    </svg>
  );
}

function IconStethoscope({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M7 4v5a5 5 0 0010 0V4" stroke="#4A9D5F" strokeWidth="1.5" fill="none" strokeLinecap="round" />
      <circle cx="7" cy="4" r="1.2" fill="#6BC07E" />
      <circle cx="17" cy="4" r="1.2" fill="#6BC07E" />
      <circle cx="17" cy="16" r="2" stroke="#4A9D5F" strokeWidth="1.2" fill="#D4F5DC" />
      <path d="M17 11v3" stroke="#4A9D5F" strokeWidth="1.2" strokeLinecap="round" />
    </svg>
  );
}

function IconPhone({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <rect x="7" y="2" width="10" height="20" rx="2" stroke="#9F6B3E" strokeWidth="1.3" fill="#FDF7EF" />
      <line x1="10" y1="19" x2="14" y2="19" stroke="#9F6B3E" strokeWidth="1" strokeLinecap="round" />
      <line x1="7" y1="5" x2="17" y2="5" stroke="#9F6B3E" strokeWidth="0.8" opacity="0.3" />
    </svg>
  );
}

function IconMoneyBag({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <path d="M9 6l3-3.5L15 6" stroke="#D4A053" strokeWidth="1.2" fill="none" strokeLinecap="round" />
      <path d="M6 10c0 0-1.5 2.5-1.5 6S7 22 12 22s7.5-2 7.5-6S18 10 18 10z" fill="#F5E6C8" stroke="#D4A053" strokeWidth="1.2" />
      <text x="12" y="17" textAnchor="middle" fill="#9F6B3E" fontSize="8" fontWeight="bold">$</text>
    </svg>
  );
}

function IconFlower({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <circle cx="12" cy="12" r="2.5" fill="#FFD6E0" stroke="#E05B8C" strokeWidth="0.8" />
      {[0, 72, 144, 216, 288].map((deg) => (
        <ellipse key={deg} cx="12" cy="6.5" rx="2.5" ry="3.5" fill="#FFB8CC" transform={`rotate(${deg} 12 12)`} opacity="0.7" />
      ))}
    </svg>
  );
}

function IconTarget({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 ${className}`} width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden>
      <circle cx="12" cy="12" r="9" stroke="#E8864B" strokeWidth="1.2" fill="none" />
      <circle cx="12" cy="12" r="6" stroke="#E8864B" strokeWidth="1" fill="#FDE8D8" />
      <circle cx="12" cy="12" r="3" stroke="#E8864B" strokeWidth="1" fill="#F5C4A0" />
      <circle cx="12" cy="12" r="1" fill="#E8864B" />
    </svg>
  );
}

function IconClipboard({ className = '' }: { className?: string }) {
  return (
    <svg className={`inline-block shrink-0 -mt-0.5 ${className}`} width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden>
      <rect x="5" y="3" width="10" height="15" rx="1.5" stroke="#9F6B3E" strokeWidth="1.3" fill="#FDF7EF" />
      <path d="M7 3V2a1 1 0 011-1h4a1 1 0 011 1v1" stroke="#9F6B3E" strokeWidth="1.2" fill="none" />
      <line x1="7.5" y1="8" x2="12.5" y2="8" stroke="#D4A053" strokeWidth="1" strokeLinecap="round" />
      <line x1="7.5" y1="11" x2="11" y2="11" stroke="#D4A053" strokeWidth="1" strokeLinecap="round" />
      <line x1="7.5" y1="14" x2="10" y2="14" stroke="#D4A053" strokeWidth="1" strokeLinecap="round" />
    </svg>
  );
}

function IconChat() {
  return (
    <svg className="inline-block shrink-0" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden>
      <path d="M3 4a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H8l-4 3v-3a2 2 0 01-1-1.7V4z" fill="rgba(255,255,255,0.3)" stroke="currentColor" strokeWidth="1.2" />
      <circle cx="7" cy="8" r="1" fill="currentColor" />
      <circle cx="10" cy="8" r="1" fill="currentColor" />
      <circle cx="13" cy="8" r="1" fill="currentColor" />
    </svg>
  );
}
