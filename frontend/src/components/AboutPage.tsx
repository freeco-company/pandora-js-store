'use client';

/**
 * /about — 全幅一體式 scrollytelling page.
 *
 * No split panels. Every section is full-width, flows naturally.
 * Scroll-triggered entrance animations (translate + opacity + blur).
 * Background color shifts between sections via CSS transitions.
 * SVG mascot evolves inline with content, not locked to a side panel.
 *
 * Reference style: artbank.tfaf.org.tw / touchstone.tw / ventiventi.tw
 */

import { useEffect, useRef, type ReactNode } from 'react';
import Link from 'next/link';
import Mascot from './Mascot';
import Icons from './SvgIcons';

/* ── Scroll-triggered reveal wrapper ─────────────────────────── */
function Reveal({ children, className = '', delay = 0 }: { children: ReactNode; className?: string; delay?: number }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setTimeout(() => el.classList.add('revealed'), delay);
          obs.unobserve(el);
        }
      },
      { threshold: 0.15, rootMargin: '0px 0px -10% 0px' },
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [delay]);

  return (
    <div
      ref={ref}
      className={`reveal-item ${className}`}
      style={{ transitionDelay: `${delay}ms` }}
    >
      {children}
    </div>
  );
}

/* ── Main page ───────────────────────────────────────────────── */
export default function AboutPage() {
  return (
    <div className="about-page">
      <style jsx global>{`
        .about-page .reveal-item {
          opacity: 0;
          transform: translateY(60px);
          filter: blur(4px);
          transition: opacity 0.9s cubic-bezier(0.22, 1, 0.36, 1),
                      transform 0.9s cubic-bezier(0.22, 1, 0.36, 1),
                      filter 0.9s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .about-page .reveal-item.revealed {
          opacity: 1;
          transform: translateY(0);
          filter: blur(0);
        }
        @media (prefers-reduced-motion: reduce) {
          .about-page .reveal-item {
            opacity: 1; transform: none; filter: none; transition: none;
          }
        }
      `}</style>

      {/* ════════════ HERO ════════════ */}
      <section className="relative min-h-screen flex items-center justify-center overflow-hidden" style={{
        background: 'linear-gradient(180deg, #1a1410 0%, #2a1f16 60%, #3d2e22 100%)',
      }}>
        {/* Giant FP watermark */}
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none select-none" aria-hidden>
          <div className="font-black text-[28vw] leading-none tracking-tighter opacity-[0.04]" style={{ color: '#e7d9cb' }}>FP</div>
        </div>

        <div className="relative text-center px-6 py-20 max-w-3xl mx-auto">
          <Reveal>
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/5 backdrop-blur-sm rounded-full border border-white/10 mb-8">
              <span className="w-1.5 h-1.5 rounded-full bg-[#f7c79a] animate-pulse" />
              <span className="text-[10px] font-black text-[#e7d9cb]/80 tracking-[0.3em]">FAIRY PANDORA</span>
            </div>
          </Reveal>
          <Reveal delay={200}>
            <h1 className="text-4xl sm:text-5xl lg:text-7xl font-black text-[#f7eee3] leading-[1.1] tracking-tight">
              從仙女
              <br />
              <span className="text-[#f7c79a]">到潘朵拉</span>
            </h1>
          </Reveal>
          <Reveal delay={400}>
            <p className="text-base sm:text-lg text-[#e7d9cb]/50 mt-6 max-w-lg mx-auto leading-relaxed">
              每位女性心裡都住著一位仙女。FP 的使命，是陪你打開那個專屬的盒子。
            </p>
          </Reveal>
          <Reveal delay={600}>
            <div className="mt-10 flex justify-center">
              <Mascot stage="seedling" mood="neutral" size={80} />
            </div>
          </Reveal>
        </div>

        {/* Scroll hint */}
        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-[#e7d9cb]/30 animate-bounce">
          <span className="text-[10px] font-bold tracking-widest">SCROLL</span>
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </div>
      </section>

      {/* ════════════ STORY: FAIRY ════════════ */}
      <section className="relative py-24 sm:py-32 overflow-hidden" style={{
        background: 'linear-gradient(180deg, #3d2e22 0%, #1e3a1e 100%)',
      }}>
        <div className="max-w-4xl mx-auto px-6 sm:px-8">
          <div className="flex flex-col md:flex-row items-center gap-10 md:gap-16">
            <Reveal className="flex-1">
              <div className="text-[10px] font-black tracking-[0.4em] text-[#8ccf8c]/60 mb-4">FAIRY · 仙女</div>
              <h2 className="text-3xl sm:text-4xl font-black text-[#f7eee3] leading-tight">
                每個女生都是仙女
              </h2>
              <p className="text-sm sm:text-base text-[#e7d9cb]/50 mt-5 leading-relaxed">
                只是有時候忘了。忘了自己可以更好、可以更自信、可以不用將就。婕樂纖是那個提醒你的契機 — 不是變成別人，而是找回自己。
              </p>
            </Reveal>
            <Reveal delay={300} className="shrink-0">
              <Mascot stage="sprout" mood="happy" size={160} />
            </Reveal>
          </div>
        </div>
      </section>

      {/* ════════════ STORY: PANDORA ════════════ */}
      <section className="relative py-24 sm:py-32 overflow-hidden" style={{
        background: 'linear-gradient(180deg, #1e3a1e 0%, #fdf7ef 100%)',
      }}>
        <div className="max-w-4xl mx-auto px-6 sm:px-8">
          <div className="flex flex-col md:flex-row-reverse items-center gap-10 md:gap-16">
            <Reveal className="flex-1">
              <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E]/60 mb-4">PANDORA · 潘朵拉</div>
              <h2 className="text-3xl sm:text-4xl font-black text-[#3d2e22] leading-tight">
                盒子裡裝的不是災難
                <br /><span className="text-[#9F6B3E]">是希望</span>
              </h2>
              <p className="text-sm sm:text-base text-[#3d2e22]/50 mt-5 leading-relaxed">
                打開它需要勇氣 — 而我們在這裡，陪你一起打開。裡面裝的是屬於你的健康、美麗、和自信。
              </p>
            </Reveal>
            <Reveal delay={300} className="shrink-0">
              <Mascot stage="bloom" mood="excited" size={160} />
            </Reveal>
          </div>
        </div>
      </section>

      {/* ════════════ JOURNEY — 4 steps ════════════ */}
      <section className="py-24 sm:py-32 bg-[#fdf7ef]">
        <div className="max-w-5xl mx-auto px-6 sm:px-8">
          <Reveal>
            <div className="text-center mb-16">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/60 mb-2">YOUR JOURNEY</div>
              <h2 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">你的蛻變旅程</h2>
            </div>
          </Reveal>
          <div className="space-y-8 sm:space-y-12">
            {[
              { step: '01', icon: Icons.Leaf, title: '遇見', desc: '第一次認識婕樂纖，對健康有了新的想像。一顆小小的種子，悄悄種在心裡。', color: 'from-[#e8f5e9] to-[#c8e6c9]', iconColor: 'text-[#4caf50]' },
              { step: '02', icon: Icons.Sparkles, title: '探索', desc: '了解三階梯定價，找到最適合自己的組合搭配。根扎得更深，葉子開始伸展。', color: 'from-[#fdf7ef] to-[#f7eee3]', iconColor: 'text-[#9F6B3E]' },
              { step: '03', icon: Icons.Heart, title: '蛻變', desc: '營養師陪伴，堅持讓改變發生。一天一天，你開始看見自己的不同。', color: 'from-[#fce4ec] to-[#f8bbd0]', iconColor: 'text-[#e91e63]' },
              { step: '04', icon: Icons.Star, title: '綻放', desc: '成為自信的潘朵拉。花開了，你把這份美好分享給身邊的人。', color: 'from-[#fff8e1] to-[#ffecb3]', iconColor: 'text-[#ff8f00]' },
            ].map((s, i) => (
              <Reveal key={s.step} delay={i * 100}>
                <div className={`flex flex-col sm:flex-row items-center gap-6 sm:gap-10 bg-gradient-to-br ${s.color} rounded-3xl p-8 sm:p-10 border border-white/50`}>
                  <div className="flex items-center gap-4 shrink-0">
                    <div className="text-[40px] sm:text-[56px] font-black text-black/[0.04] leading-none">{s.step}</div>
                    <div className={`w-14 h-14 rounded-2xl bg-white/70 flex items-center justify-center ${s.iconColor}`}>
                      <s.icon className="w-7 h-7" />
                    </div>
                  </div>
                  <div>
                    <h3 className="text-xl sm:text-2xl font-black text-[#3d2e22]">{s.title}</h3>
                    <p className="text-sm sm:text-base text-gray-600 mt-2 leading-relaxed">{s.desc}</p>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* ════════════ TEAM ════════════ */}
      <section className="py-24 sm:py-32 bg-white">
        <div className="max-w-5xl mx-auto px-6 sm:px-8">
          <Reveal>
            <div className="text-center mb-16">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/60 mb-2">WHO WE ARE</div>
              <h2 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
            </div>
          </Reveal>
          <div className="grid md:grid-cols-3 gap-6">
            {[
              {
                Icon: Icons.Seedling, iconColor: 'text-[#9F6B3E]',
                bg: 'from-[#fdf7ef] to-[#f7eee3]', border: 'border-[#e7d9cb]',
                badge: 'CO-FOUNDER', badgeColor: 'text-[#9F6B3E]',
                name: '朵朵', creds: ['健康食品業 8 年', '台灣電商創業家'],
                bio: '從自己體驗過婕樂纖開始，因為真的看到身邊的人改變，決定創辦仙女館 FP。用「從仙女變成 Pandora」的精神，把好東西帶給更多女性。',
              },
              {
                Icon: Icons.Leaf, iconColor: 'text-[#2e7d32]',
                bg: 'from-[#e8f5e9] to-[#f1f8e9]', border: 'border-[#c8e6c9]',
                badge: 'NUTRITION TEAM', badgeColor: 'text-[#2e7d32]',
                name: '營養師顧問團', creds: ['專技高考合格營養師', '食品科學碩士'],
                bio: '由合格營養師組成的陪伴團隊。纖體系列滿額即可加入陪伴班或陪跑班，享有專屬飲食指導與持續追蹤。',
              },
              {
                Icon: Icons.Gift, iconColor: 'text-[#1565c0]',
                bg: 'from-[#e3f2fd] to-[#bbdefb]', border: 'border-[#90caf9]',
                badge: 'CUSTOMER CARE', badgeColor: 'text-[#1565c0]',
                name: '客服仙女', creds: ['1 對 1 LINE 諮詢', '週一至週五上線'],
                bio: '每位顧客背後都有專屬客服仙女，從下單到售後、產品諮詢全包辦。平均 1 小時內回覆訊息。',
              },
            ].map((m, i) => (
              <Reveal key={m.name} delay={i * 150}>
                <div className={`bg-gradient-to-br ${m.bg} rounded-3xl p-8 ${m.border} border text-center h-full`}>
                  <div className={`w-16 h-16 mx-auto rounded-full bg-white/80 flex items-center justify-center ${m.iconColor} shadow-lg mb-5`}>
                    <m.Icon className="w-8 h-8" />
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
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* ════════════ VALUES ════════════ */}
      <section className="py-24 sm:py-32" style={{ background: 'linear-gradient(180deg, #fff 0%, #fdf7ef 100%)' }}>
        <div className="max-w-4xl mx-auto px-6 sm:px-8">
          <Reveal>
            <div className="text-center mb-16">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/60 mb-2">OUR VALUES</div>
              <h2 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">我們相信</h2>
            </div>
          </Reveal>
          <div className="grid sm:grid-cols-3 gap-6">
            {[
              { Icon: Icons.Shield, color: 'text-[#9F6B3E]', title: '正品堅持', desc: 'JEROSSE 官方授權經銷，由婕樂纖仙女館出貨。每件都是 JEROSSE 正品，與品牌官網販售商品一致。' },
              { Icon: Icons.Heart, color: 'text-[#e91e63]', title: '真心陪伴', desc: '不催促、不話術。你想了解，我就說；你想等等，我就等。陪伴不是壓力。' },
              { Icon: Icons.Star, color: 'text-[#ff8f00]', title: '長期價值', desc: '健康不是一次性消費。三階梯定價讓你買越多越划算，營養師陪伴讓改變持續。' },
            ].map((v, i) => (
              <Reveal key={v.title} delay={i * 100}>
                <div className="bg-white rounded-3xl p-8 border border-[#e7d9cb] h-full hover:shadow-lg hover:-translate-y-1 transition-all duration-500">
                  <div className={`w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center ${v.color} mb-4`}>
                    <v.Icon className="w-6 h-6" />
                  </div>
                  <h3 className="text-lg font-black text-[#3d2e22]">{v.title}</h3>
                  <p className="text-sm text-gray-600 mt-3 leading-relaxed">{v.desc}</p>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* ════════════ CTA ════════════ */}
      <section className="relative py-24 sm:py-32 overflow-hidden" style={{
        background: 'linear-gradient(180deg, #9F6B3E 0%, #6b4424 100%)',
      }}>
        <div className="absolute inset-0 opacity-10 pointer-events-none">
          <div className="absolute top-10 left-[10%] w-32 h-32 rounded-full bg-white/20 blur-3xl" />
          <div className="absolute bottom-10 right-[15%] w-48 h-48 rounded-full bg-white/10 blur-3xl" />
        </div>
        <div className="relative max-w-3xl mx-auto px-6 sm:px-8 text-center">
          <Reveal>
            <Mascot stage="bloom" mood="excited" size={120} />
          </Reveal>
          <Reveal delay={200}>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight mt-8">
              準備好打開你的
              <br />潘朵拉盒子了嗎？
            </h2>
          </Reveal>
          <Reveal delay={400}>
            <p className="text-sm sm:text-base text-white/60 mt-5 max-w-md mx-auto">
              每個蛻變都從一小步開始。無論你是第一次認識婕樂纖，或是想找到更適合自己的組合 — 我們都在這裡。
            </p>
          </Reveal>
          <Reveal delay={600}>
            <div className="mt-10 flex flex-col sm:flex-row gap-3 justify-center">
              <Link href="/products" className="px-8 py-4 bg-white text-[#9F6B3E] font-black rounded-full hover:bg-white/90 transition-all shadow-xl min-h-[52px] flex items-center justify-center">
                開始選購
              </Link>
              <Link href="/articles" className="px-8 py-4 bg-white/10 backdrop-blur text-white font-black rounded-full hover:bg-white/20 transition-all border border-white/20 min-h-[52px] flex items-center justify-center">
                閱讀仙女誌 →
              </Link>
            </div>
          </Reveal>
        </div>
      </section>
    </div>
  );
}
