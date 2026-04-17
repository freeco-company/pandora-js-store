import type { Metadata } from 'next';
import Link from 'next/link';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import Mascot from '@/components/Mascot';
import ScrollMascotGrowth from '@/components/ScrollMascotGrowth';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '關於 FP｜從仙女到潘朵拉的蛻變之旅',
  description:
    'Fairy Pandora — 不只是品牌名，是每位女性從認識自己開始，打開專屬美麗盒子的旅程。認識 FP 團隊與我們的品牌故事。',
  alternates: { canonical: '/about' },
};

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora-dev.js-store.com.tw';

const teamJsonLd = [{
  '@context': 'https://schema.org', '@type': 'Person',
  '@id': `${siteUrl}/about#duoduo`, name: '朵朵',
  jobTitle: 'Co-Founder · 婕樂纖仙女館',
  worksFor: { '@id': `${siteUrl}/#organization` },
}];

/* ─── SVG Icons (replacing emoji for professional look) ─────────── */
const Icon = {
  Leaf: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M17 8c-4 0-8 4-8 8a8 8 0 008-8z" /><path d="M9 16c0-4 4-8 8-8" /><path d="M3 21s2-4 5-4" />
    </svg>
  ),
  Sparkles: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 3l1.5 5.5L19 10l-5.5 1.5L12 17l-1.5-5.5L5 10l5.5-1.5z" />
      <path d="M18 18l.5 1.5L20 20l-1.5.5L18 22l-.5-1.5L16 20l1.5-.5z" />
    </svg>
  ),
  Trophy: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M6 9H4.5a2.5 2.5 0 010-5H6" /><path d="M18 9h1.5a2.5 2.5 0 000-5H18" />
      <path d="M4 22h16" /><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20 7 22h10c0-2-0.85-3.25-2.03-3.79A1.09 1.09 0 0114 17v-2.34" />
      <path d="M18 2H6v7a6 6 0 1012 0V2z" />
    </svg>
  ),
  Shield: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><path d="M9 12l2 2 4-4" />
    </svg>
  ),
  Heart: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.3 1.5 4.05 3 5.5l7 7 7-7z" />
    </svg>
  ),
  Star: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
    </svg>
  ),
  Nutrition: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M2 2l10 6 10-6" /><path d="M12 8v13" /><path d="M20 21H4a2 2 0 01-2-2V8l10 6 10-6v11a2 2 0 01-2 2z" />
    </svg>
  ),
  Chat: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
    </svg>
  ),
  Fairy: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M12 3c-4 0-7 3-7 7s7 11 7 11 7-7 7-11-3-7-7-7z" /><circle cx="12" cy="10" r="3" />
    </svg>
  ),
  Box: (cls = 'w-7 h-7') => (
    <svg className={cls} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
      <path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z" />
      <polyline points="3.27 6.96 12 12.01 20.73 6.96" /><line x1="12" y1="22.08" x2="12" y2="12" />
    </svg>
  ),
};

export default function AboutPage() {
  const breadcrumbs = breadcrumbSchema([{ name: '首頁', url: '/' }, { name: '關於 FP' }]);

  return (
    <div className="relative">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbs, ...teamJsonLd) }} />

      {/* ═══ HERO ═══ */}
      <section className="relative min-h-[70vh] sm:min-h-[80vh] flex items-center overflow-hidden">
        <div className="absolute inset-0" style={{
          background:
            'radial-gradient(ellipse at 30% 20%, rgba(247,199,154,0.3), transparent 60%),' +
            'radial-gradient(ellipse at 70% 80%, rgba(159,107,62,0.15), transparent 50%),' +
            'linear-gradient(180deg, #e7d9cb 0%, #f7eee3 40%, #ffffff 100%)',
        }} />
        <FloatingShapes />
        <div className="relative w-full max-w-5xl mx-auto px-5 sm:px-8 lg:px-12 py-16 sm:py-24">
          <div className="flex flex-col lg:flex-row items-center gap-10 lg:gap-16">
            <div className="flex items-end gap-3 sm:gap-4 shrink-0">
              <ScrollReveal variant="fade-up" delay={0}>
                <div className="text-center opacity-50">
                  <Mascot stage="seedling" mood="neutral" size={56} />
                  <div className="text-[9px] font-bold text-gray-400 mt-1">萌芽</div>
                </div>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={150}>
                <div className="text-center opacity-70">
                  <Mascot stage="sprout" mood="happy" size={80} />
                  <div className="text-[10px] font-bold text-[#9F6B3E] mt-1">成長</div>
                </div>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={300}>
                <div className="text-center">
                  <Mascot stage="bloom" mood="excited" size={110} />
                  <div className="text-[11px] font-black text-[#9F6B3E] mt-1">綻放</div>
                </div>
              </ScrollReveal>
            </div>
            <div className="text-center lg:text-left flex-1">
              <ScrollReveal variant="fade-up">
                <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/70 backdrop-blur rounded-full border border-white/80 shadow-sm mb-5">
                  <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
                  <span className="text-[10px] font-black text-[#9F6B3E] tracking-[0.25em]">FAIRY PANDORA</span>
                </div>
              </ScrollReveal>
              <TextReveal as="h1" text="從仙女，到潘朵拉" className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22] tracking-tight leading-tight" stagger={80} />
              <ScrollReveal variant="fade-up" delay={400}>
                <p className="text-base sm:text-lg text-gray-600 mt-5 max-w-lg leading-relaxed">
                  每位女性心裡都住著一位仙女。<br className="hidden sm:block" />
                  FP 的使命，是陪你打開那個專屬的盒子 —<br className="hidden sm:block" />
                  <strong className="text-[#9F6B3E]">從認識自己，到綻放成最好的自己。</strong>
                </p>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={600}>
                <div className="mt-8">
                  <Link href="/products" className="inline-flex items-center gap-2 px-7 py-3.5 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-all shadow-lg shadow-[#9F6B3E]/20 min-h-[48px]">
                    開始我的蛻變旅程 →
                  </Link>
                </div>
              </ScrollReveal>
            </div>
          </div>
        </div>
      </section>

      {/* ═══ OUR STORY ═══ */}
      <section className="bg-white py-16 sm:py-24">
        <div className="max-w-4xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">OUR STORY</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">為什麼叫 Fairy Pandora？</h2>
            </div>
          </ScrollReveal>
          <div className="grid md:grid-cols-2 gap-8">
            <ScrollReveal variant="fade-up" delay={100}>
              <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-8 border border-[#e7d9cb] h-full">
                <div className="w-14 h-14 rounded-2xl bg-white/80 flex items-center justify-center text-[#9F6B3E] shadow-sm mb-4">
                  {Icon.Fairy('w-8 h-8')}
                </div>
                <h3 className="text-lg font-black text-[#9F6B3E]">Fairy · 仙女</h3>
                <p className="text-sm text-gray-600 mt-3 leading-relaxed">
                  每個女生都是仙女，只是有時候忘了。婕樂纖是我們找回自信的起點 — 不是變成別人，而是成為更好的自己。
                </p>
              </div>
            </ScrollReveal>
            <ScrollReveal variant="fade-up" delay={250}>
              <div className="bg-gradient-to-br from-[#f7eee3] to-[#e7d9cb] rounded-3xl p-8 border border-[#d4c4b0] h-full">
                <div className="w-14 h-14 rounded-2xl bg-white/80 flex items-center justify-center text-[#85572F] shadow-sm mb-4">
                  {Icon.Box('w-8 h-8')}
                </div>
                <h3 className="text-lg font-black text-[#85572F]">Pandora · 潘朵拉</h3>
                <p className="text-sm text-gray-600 mt-3 leading-relaxed">
                  潘朵拉的盒子不是災難，是希望。打開它需要勇氣 — 而我們在這裡，陪你一起打開。裡面裝的是屬於你的健康、美麗、和自信。
                </p>
              </div>
            </ScrollReveal>
          </div>
        </div>
      </section>

      {/* ═══ JOURNEY — scroll-driven mascot growth ═══ */}
      <section className="py-16 sm:py-24 relative" style={{ background: 'linear-gradient(180deg, #fff 0%, #fdf7ef 50%, #fff 100%)' }}>
        <div className="max-w-5xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-8">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">YOUR JOURNEY</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">你的蛻變旅程</h2>
              <p className="text-sm text-gray-500 mt-2">向下捲動，看芽芽跟你一起成長</p>
            </div>
          </ScrollReveal>

          {/* Desktop: mascot left, steps right. Mobile: mascot center, steps below */}
          <div className="flex flex-col lg:flex-row items-start gap-8 lg:gap-12">
            {/* Scroll-driven mascot — sticky on desktop */}
            <div className="w-full lg:w-auto lg:sticky lg:top-24 flex justify-center lg:block shrink-0">
              <ScrollMascotGrowth size={220} className="drop-shadow-xl" />
            </div>

            {/* 4 journey steps */}
            <div className="flex-1 space-y-6">
              {[
                { step: '01', icon: Icon.Leaf, title: '遇見', desc: '第一次認識婕樂纖，對健康有了新的想像。一顆小小的種子，悄悄種在心裡。', color: 'from-[#e8f5e9] to-[#c8e6c9]', iconColor: 'text-[#4caf50]' },
                { step: '02', icon: Icon.Sparkles, title: '探索', desc: '了解三階梯定價，搭配最適合自己的組合。根扎得更深，葉子開始伸展。', color: 'from-[#fdf7ef] to-[#f7eee3]', iconColor: 'text-[#9F6B3E]' },
                { step: '03', icon: Icon.Heart, title: '蛻變', desc: '營養師陪伴，堅持讓改變發生。一天一天，你開始看見自己的不同。', color: 'from-[#fce4ec] to-[#f8bbd0]', iconColor: 'text-[#e91e63]' },
                { step: '04', icon: Icon.Star, title: '綻放', desc: '成為自信的潘朵拉。花開了，你把這份美好分享給身邊的人。', color: 'from-[#fff8e1] to-[#ffecb3]', iconColor: 'text-[#ff8f00]' },
              ].map((s, i) => (
                <ScrollReveal key={s.step} variant="fade-up" delay={i * 80}>
                  <div className={`bg-gradient-to-br ${s.color} rounded-3xl p-6 sm:p-8 border border-white/50 hover:shadow-lg transition-all`}>
                    <div className="flex items-start gap-4">
                      <div className={`w-12 h-12 rounded-xl bg-white/70 flex items-center justify-center ${s.iconColor} shrink-0`}>
                        {s.icon('w-6 h-6')}
                      </div>
                      <div>
                        <div className="text-[10px] font-black tracking-[0.2em] text-gray-400 mb-1">STEP {s.step}</div>
                        <h3 className="text-lg font-black text-[#3d2e22]">{s.title}</h3>
                        <p className="text-sm text-gray-600 mt-2 leading-relaxed">{s.desc}</p>
                      </div>
                    </div>
                  </div>
                </ScrollReveal>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ═══ TEAM ═══ */}
      <section className="bg-white py-16 sm:py-24">
        <div className="max-w-5xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">WHO WE ARE</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
            </div>
          </ScrollReveal>
          <div className="grid md:grid-cols-3 gap-6">
            {[
              {
                icon: Icon.Fairy, iconColor: 'text-[#9F6B3E]',
                bg: 'from-[#fdf7ef] to-[#f7eee3]', border: 'border-[#e7d9cb]',
                badge: 'CO-FOUNDER', badgeColor: 'text-[#9F6B3E]',
                name: '朵朵', creds: ['健康食品業 8 年', '台灣電商創業家'],
                bio: '從自己體驗過婕樂纖開始，因為真的看到身邊的人改變，決定創辦仙女館 FP。用「從仙女變成 Pandora」的精神，把好東西帶給更多女性。',
              },
              {
                icon: Icon.Nutrition, iconColor: 'text-[#2e7d32]',
                bg: 'from-[#e8f5e9] to-[#f1f8e9]', border: 'border-[#c8e6c9]',
                badge: 'NUTRITION TEAM', badgeColor: 'text-[#2e7d32]',
                name: '營養師顧問團', creds: ['專技高考合格營養師', '食品科學碩士'],
                bio: '由合格營養師組成的陪伴團隊。纖體系列滿額即可加入陪伴班或陪跑班，享有專屬飲食指導與持續追蹤。',
              },
              {
                icon: Icon.Chat, iconColor: 'text-[#1565c0]',
                bg: 'from-[#e3f2fd] to-[#bbdefb]', border: 'border-[#90caf9]',
                badge: 'CUSTOMER CARE', badgeColor: 'text-[#1565c0]',
                name: '客服仙女', creds: ['1 對 1 LINE 諮詢', '週一至週五上線'],
                bio: '每位顧客背後都有專屬客服仙女，從下單到售後、產品諮詢全包辦。平均 1 小時內回覆訊息。',
              },
            ].map((m, i) => (
              <ScrollReveal key={m.name} variant="fade-up" delay={i * 150}>
                <div className={`bg-gradient-to-br ${m.bg} rounded-3xl p-6 sm:p-8 ${m.border} border text-center h-full`}>
                  <div className={`w-20 h-20 mx-auto rounded-full bg-white/80 flex items-center justify-center ${m.iconColor} shadow-lg mb-4`}>
                    {m.icon('w-10 h-10')}
                  </div>
                  <div className={`text-[10px] font-black tracking-[0.2em] ${m.badgeColor} mb-1`}>{m.badge}</div>
                  <h3 className="text-xl font-black text-[#3d2e22]">{m.name}</h3>
                  <div className="flex flex-wrap justify-center gap-1.5 mt-3">
                    {m.creds.map((c) => (
                      <span key={c} className="px-2 py-0.5 rounded-full bg-white/80 text-[10px] font-bold text-gray-600">{c}</span>
                    ))}
                  </div>
                  <p className="text-sm text-gray-600 mt-4 leading-relaxed">{m.bio}</p>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ VALUES ═══ */}
      <section className="py-16 sm:py-24" style={{ background: 'linear-gradient(180deg, #fff 0%, #fdf7ef 100%)' }}>
        <div className="max-w-4xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">OUR VALUES</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">我們相信</h2>
            </div>
          </ScrollReveal>
          <div className="grid sm:grid-cols-3 gap-5">
            {[
              { icon: Icon.Shield, color: 'text-[#9F6B3E]', title: '正品堅持', desc: 'JEROSSE 官方授權經銷，每一件都是原廠出貨。你買到的，跟品牌官網完全一樣。' },
              { icon: Icon.Heart, color: 'text-[#e91e63]', title: '真心陪伴', desc: '不催促、不話術。你想了解，我就說；你想等等，我就等。陪伴不是壓力。' },
              { icon: Icon.Star, color: 'text-[#ff8f00]', title: '長期價值', desc: '健康不是一次性消費。三階梯定價讓你買越多越划算，營養師陪伴讓改變持續。' },
            ].map((v, i) => (
              <ScrollReveal key={v.title} variant="fade-up" delay={i * 100}>
                <div className="bg-white rounded-2xl p-6 border border-[#e7d9cb] h-full hover:shadow-md transition-shadow">
                  <div className={`w-11 h-11 rounded-xl bg-gray-50 flex items-center justify-center ${v.color} mb-3`}>
                    {v.icon('w-6 h-6')}
                  </div>
                  <h3 className="text-base font-black text-[#3d2e22]">{v.title}</h3>
                  <p className="text-sm text-gray-600 mt-2 leading-relaxed">{v.desc}</p>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ CTA ═══ */}
      <section className="bg-gradient-to-br from-[#9F6B3E] via-[#85572F] to-[#6b4424] text-white py-16 sm:py-24 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10"><FloatingShapes /></div>
        <div className="relative max-w-3xl mx-auto px-5 sm:px-8 text-center">
          <ScrollReveal variant="zoom-in">
            <div className="inline-block mb-6"><Mascot stage="bloom" mood="excited" size={120} /></div>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={200}>
            <h2 className="text-2xl sm:text-4xl font-black leading-tight">
              準備好打開你的<br />潘朵拉盒子了嗎？
            </h2>
            <p className="text-sm sm:text-base text-white/80 mt-4 max-w-md mx-auto">
              每個蛻變都從一小步開始。無論你是第一次認識婕樂纖，或是想找到更適合自己的組合 — 我們都在這裡。
            </p>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={400}>
            <div className="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
              <Link href="/products" className="px-8 py-4 bg-white text-[#9F6B3E] font-black rounded-full hover:bg-white/90 transition-all shadow-xl min-h-[52px] flex items-center justify-center">
                開始選購
              </Link>
              <Link href="/articles" className="px-8 py-4 bg-white/15 backdrop-blur text-white font-black rounded-full hover:bg-white/25 transition-all border border-white/20 min-h-[52px] flex items-center justify-center">
                閱讀仙女誌 →
              </Link>
            </div>
          </ScrollReveal>
        </div>
      </section>
    </div>
  );
}
