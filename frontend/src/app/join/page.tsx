'use client';

import Link from 'next/link';
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
            text="兩種加入方式"
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-xl mx-auto leading-relaxed">
              不論是<strong className="text-[#9F6B3E]">自用省錢</strong>，還是<strong className="text-[#9F6B3E]">想發展副業</strong>，FP 都準備好陪你一起。
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
            🌱 自用加盟
          </button>
          <button
            onClick={() => setTab('business')}
            className={`py-3 px-4 rounded-full text-sm font-black transition-all ${
              tab === 'business'
                ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-md'
                : 'text-[#7a5836] hover:bg-white/50'
            }`}
          >
            💎 創業加盟
          </button>
        </div>

        {tab === 'self' ? <SelfUsePanel /> : <BusinessPanel />}
      </div>
    </div>
  );
}

function SelfUsePanel() {
  return (
    <div className="space-y-8">
      <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-10 border border-[#e7d9cb]">
        <div className="flex items-center gap-3 mb-4">
          <span className="text-5xl">🌱</span>
          <div>
            <div className="text-[10px] font-black tracking-[0.3em] text-[#7a5836]">SELF-USE · 自用優惠</div>
            <h2 className="text-2xl sm:text-3xl font-black text-gray-900 mt-1">自用加盟 · NT$6,600</h2>
          </div>
        </div>
        <p className="text-sm sm:text-base text-gray-700 leading-relaxed mb-6">
          一次 <strong className="text-[#9F6B3E]">NT$6,600</strong>，全館商品永久享加盟會員價。
          <br className="hidden sm:inline" />
          適合每月固定回購、想為自己省錢的仙女。
        </p>

        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {[
            { i: '💰', t: '立即回本', d: '購買 2-3 盒主力商品就回本' },
            { i: '♾️', t: '永久有效', d: '一次費用，之後都享會員價' },
            { i: '🚫', t: '零業績壓力', d: '只為自己，不強制分享' },
          ].map((b, i) => (
            <div key={i} className="bg-white rounded-2xl p-4 border border-[#e7d9cb]">
              <div className="text-2xl mb-2">{b.i}</div>
              <div className="font-black text-gray-900 text-sm mb-1">{b.t}</div>
              <div className="text-xs text-gray-500">{b.d}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e7d9cb]">
        <h3 className="text-lg font-black text-gray-900 mb-4">📋 加盟流程</h3>
        <ol className="space-y-3">
          {[
            '私訊 LINE 或 IG 諮詢',
            '完成 NT$6,600 繳費',
            '簽署加盟同意書（線上）',
            '獲得會員專屬下單連結',
            '永久享受加盟會員價',
          ].map((step, i) => (
            <li key={i} className="flex items-start gap-3">
              <span className="w-7 h-7 rounded-full bg-[#9F6B3E] text-white font-black text-sm flex items-center justify-center shrink-0">
                {i + 1}
              </span>
              <span className="text-sm text-gray-800 pt-1">{step}</span>
            </li>
          ))}
        </ol>
      </div>

      <div className="text-center pt-4">
        <a
          href="https://lin.ee/pandorasdo"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 px-10 py-4 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg hover:bg-[#85572F] transition-colors min-h-[52px]"
        >
          💬 LINE 諮詢加入
        </a>
        <p className="text-xs text-gray-500 mt-3">或先追蹤 IG <a href="https://www.instagram.com/pandorasdo/" target="_blank" rel="noopener noreferrer" className="font-black text-[#9F6B3E] underline">@pandorasdo</a></p>
      </div>
    </div>
  );
}

function BusinessPanel() {
  return (
    <div className="space-y-8">
      <div className="bg-gradient-to-br from-[#fef6e4] via-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-10 border border-[#9F6B3E]/30">
        <div className="flex items-center gap-3 mb-4">
          <span className="text-5xl">💎</span>
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
            { i: '📚', t: '系統化培訓', d: '產品、社群、服務、話術 40+ 小時課程' },
            { i: '👩‍⚕️', t: '營養師支援', d: '每月 2 場線上直播，客戶問題即時解' },
            { i: '📱', t: '現成素材庫', d: '貼文模板、直播腳本、圖文素材一應俱全' },
            { i: '💸', t: '階梯式獎金', d: '隨業績與團隊規模成長，獎金結構清楚' },
            { i: '🌸', t: '團隊陪伴', d: '月聚會、季旅遊、年度仙女大會' },
            { i: '🎯', t: '個人品牌打造', d: '協助你建立 IG / 直播 / 個人定位' },
          ].map((b, i) => (
            <div key={i} className="bg-white rounded-2xl p-4 border border-[#e7d9cb]">
              <div className="text-2xl mb-2">{b.i}</div>
              <div className="font-black text-gray-900 text-sm mb-1">{b.t}</div>
              <div className="text-xs text-gray-500 leading-relaxed">{b.d}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="bg-white rounded-3xl p-6 sm:p-8 border border-[#e7d9cb]">
        <h3 className="text-lg font-black text-gray-900 mb-4">📋 創業加盟流程</h3>
        <ol className="space-y-3">
          {[
            '私訊 LINE / IG 說明你的想法',
            '1 對 1 視訊面談（了解你的期待與時間）',
            '加盟諮詢（說明階梯等級、獎金結構）',
            '完成簽約與開通',
            '加入新手培訓群組，開始 4 週入門課程',
            '第一個月有督導 1 對 1 陪伴',
          ].map((step, i) => (
            <li key={i} className="flex items-start gap-3">
              <span className="w-7 h-7 rounded-full bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black text-sm flex items-center justify-center shrink-0">
                {i + 1}
              </span>
              <span className="text-sm text-gray-800 pt-1">{step}</span>
            </li>
          ))}
        </ol>
      </div>

      <div className="bg-[#1a1410] text-[#f7eee3] rounded-3xl p-6 sm:p-10 relative overflow-hidden">
        <span className="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-[#9F6B3E]/30 blur-3xl" />
        <div className="relative">
          <div className="text-[10px] font-black tracking-[0.3em] text-[#e7d9cb]/60 mb-2">FOUNDER SAYS</div>
          <p className="text-base sm:text-lg leading-relaxed font-bold mb-4">
            &ldquo;我不會答應你一夜致富，但我會陪你打造能放 10 年的事業。&rdquo;
          </p>
          <div className="text-sm text-[#9F6B3E] font-black">— 朵朵 · Co-Founder</div>
        </div>
      </div>

      <div className="text-center pt-4">
        <a
          href="https://lin.ee/pandorasdo"
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-2 px-10 py-4 bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white font-black rounded-full shadow-lg hover:from-[#85572F] hover:to-[#6b4424] transition-colors min-h-[52px]"
        >
          💬 LINE 預約視訊面談
        </a>
        <p className="text-xs text-gray-500 mt-3 max-w-sm mx-auto">
          面談免費、無壓力。了解清楚再決定，FP 不催不逼。
        </p>
      </div>
    </div>
  );
}
