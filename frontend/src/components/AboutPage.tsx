'use client';

/**
 * /about — premium scrollytelling page.
 *
 * Techniques matching artbank / touchstone / ventiventi / fenc:
 * - Lenis smooth scroll (global, via LenisProvider)
 * - 0.8-1.5s transitions with cubic-bezier(.33, 1, .68, 1)
 * - scale(1.15→1) image reveals in overflow-hidden
 * - clip-path: inset(12%→0%) section reveals
 * - blur(8px→0) + translateY(40px→0) entrance
 * - mix-blend-difference text overlay on CTA
 * - Staggered children 120ms apart
 */

import { useEffect, useRef, type ReactNode } from 'react';
import Link from 'next/link';
import Mascot from './Mascot';
import Icons from './SvgIcons';

/* ── Premium reveal ──────────────────────────────────────────── */
function Reveal({
  children,
  className = '',
  delay = 0,
  variant = 'up', // up | scale | clip
}: {
  children: ReactNode;
  className?: string;
  delay?: number;
  variant?: 'up' | 'scale' | 'clip';
}) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setTimeout(() => el.classList.add('is-visible'), delay);
          obs.unobserve(el);
        }
      },
      { threshold: 0.1, rootMargin: '0px 0px -8% 0px' },
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [delay]);

  return (
    <div ref={ref} className={`fp-reveal fp-reveal--${variant} ${className}`}>
      {children}
    </div>
  );
}

/* ── Stagger container: each child gets incremented delay ───── */
function Stagger({ children, gap = 120, className = '' }: { children: ReactNode; gap?: number; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const obs = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          const items = el.querySelectorAll('.fp-stagger-child');
          items.forEach((item, i) => {
            setTimeout(() => item.classList.add('is-visible'), i * gap);
          });
          obs.unobserve(el);
        }
      },
      { threshold: 0.1 },
    );
    obs.observe(el);
    return () => obs.disconnect();
  }, [gap]);

  return <div ref={ref} className={className}>{children}</div>;
}

function StaggerChild({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`fp-stagger-child fp-reveal fp-reveal--up ${className}`}>{children}</div>;
}

/* ════════════════════════════════════════════════════════════════ */
export default function AboutPage() {
  return (
    <div className="about-premium">
      {/* Global premium animation styles */}
      <style jsx global>{`
        .about-premium .fp-reveal {
          will-change: transform, opacity, filter, clip-path;
        }
        .about-premium .fp-reveal--up {
          opacity: 0;
          transform: translateY(40px);
          filter: blur(8px);
          transition: opacity 0.9s cubic-bezier(.33, 1, .68, 1),
                      transform 0.9s cubic-bezier(.33, 1, .68, 1),
                      filter 1.2s cubic-bezier(.33, 1, .68, 1);
        }
        .about-premium .fp-reveal--up.is-visible {
          opacity: 1;
          transform: translateY(0);
          filter: blur(0);
        }
        .about-premium .fp-reveal--scale {
          opacity: 0;
          transform: scale(0.85);
          filter: blur(6px);
          transition: opacity 1s cubic-bezier(.33, 1, .68, 1),
                      transform 1.2s cubic-bezier(.33, 1, .68, 1),
                      filter 1.2s cubic-bezier(.33, 1, .68, 1);
        }
        .about-premium .fp-reveal--scale.is-visible {
          opacity: 1;
          transform: scale(1);
          filter: blur(0);
        }
        .about-premium .fp-reveal--clip {
          clip-path: inset(12% round 24px);
          transition: clip-path 1.2s cubic-bezier(.33, 1, .68, 1);
        }
        .about-premium .fp-reveal--clip.is-visible {
          clip-path: inset(0% round 0px);
        }
        .about-premium .fp-stagger-child {
          transition-delay: 0ms;
        }
        .about-premium .fp-img-reveal {
          overflow: hidden;
        }
        .about-premium .fp-img-reveal > * {
          transform: scale(1.15);
          transition: transform 1.4s cubic-bezier(.33, 1, .68, 1);
        }
        .about-premium .fp-img-reveal.is-visible > * {
          transform: scale(1);
        }
        @media (prefers-reduced-motion: reduce) {
          .about-premium .fp-reveal,
          .about-premium .fp-stagger-child,
          .about-premium .fp-img-reveal > * {
            opacity: 1 !important; transform: none !important;
            filter: none !important; clip-path: none !important;
            transition: none !important;
          }
        }
      `}</style>

      {/* ════════ HERO ════════ */}
      <section className="relative min-h-screen flex items-center justify-center overflow-hidden" style={{
        background: 'linear-gradient(180deg, #1a1410 0%, #2a1f16 60%, #3d2e22 100%)',
      }}>
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none select-none" aria-hidden>
          <div className="font-black text-[28vw] leading-none tracking-tighter opacity-[0.03]" style={{ color: '#e7d9cb' }}>FP</div>
        </div>
        <div className="relative text-center px-6 py-20 max-w-3xl mx-auto">
          <Reveal delay={100}>
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-white/5 backdrop-blur-sm rounded-full border border-white/10 mb-8">
              <span className="w-1.5 h-1.5 rounded-full bg-[#f7c79a] animate-pulse" />
              <span className="text-[10px] font-black text-[#e7d9cb]/80 tracking-[0.3em]">FAIRY PANDORA</span>
            </div>
          </Reveal>
          <Reveal delay={300}>
            <h1 className="text-4xl sm:text-6xl lg:text-7xl font-black text-[#f7eee3] leading-[1.05] tracking-tight">
              從仙女
              <br />
              <span className="text-[#f7c79a]">到潘朵拉</span>
            </h1>
          </Reveal>
          <Reveal delay={500}>
            <p className="text-base sm:text-lg text-[#e7d9cb]/40 mt-6 max-w-lg mx-auto leading-relaxed">
              每位女性心裡都住著一位仙女。FP 的使命，是陪你打開那個專屬的盒子。
            </p>
          </Reveal>
          <Reveal delay={700}>
            <div className="mt-12 flex justify-center">
              <Mascot stage="seedling" mood="neutral" size={80} />
            </div>
          </Reveal>
        </div>
        <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-[#e7d9cb]/25 animate-bounce">
          <span className="text-[9px] font-bold tracking-[0.3em]">SCROLL</span>
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
          </svg>
        </div>
      </section>

      {/* ════════ FAIRY — clip-path reveal ════════ */}
      <Reveal variant="clip">
        <section className="py-28 sm:py-36 overflow-hidden" style={{
          background: 'linear-gradient(180deg, #1e3a1e 0%, #2a4a2a 100%)',
        }}>
          <div className="max-w-5xl mx-auto px-6 sm:px-8">
            <div className="flex flex-col md:flex-row items-center gap-12 md:gap-20">
              <Reveal className="flex-1" delay={200}>
                <div className="text-[10px] font-black tracking-[0.4em] text-[#8ccf8c]/50 mb-4">FAIRY · 仙女</div>
                <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#f7eee3] leading-[1.1]">
                  每個女生<br />都是仙女
                </h2>
                <p className="text-sm sm:text-base text-[#e7d9cb]/40 mt-6 leading-relaxed max-w-md">
                  只是有時候忘了。忘了自己可以更好、可以更自信、可以不用將就。婕樂纖是那個提醒你的契機 — 不是變成別人，而是找回自己。
                </p>
              </Reveal>
              <Reveal variant="scale" delay={400} className="shrink-0">
                <Mascot stage="sprout" mood="happy" size={200} />
              </Reveal>
            </div>
          </div>
        </section>
      </Reveal>

      {/* ════════ PANDORA — clip-path reveal (reversed) ════════ */}
      <Reveal variant="clip">
        <section className="py-28 sm:py-36 overflow-hidden" style={{
          background: 'linear-gradient(180deg, #fdf7ef 0%, #f7eee3 100%)',
        }}>
          <div className="max-w-5xl mx-auto px-6 sm:px-8">
            <div className="flex flex-col md:flex-row-reverse items-center gap-12 md:gap-20">
              <Reveal className="flex-1" delay={200}>
                <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E]/50 mb-4">PANDORA · 潘朵拉</div>
                <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22] leading-[1.1]">
                  盒子裡裝的<br />不是災難<br /><span className="text-[#9F6B3E]">是希望</span>
                </h2>
                <p className="text-sm sm:text-base text-[#3d2e22]/40 mt-6 leading-relaxed max-w-md">
                  打開它需要勇氣 — 而我們在這裡，陪你一起打開。裡面裝的是屬於你的健康、美麗、和自信。
                </p>
              </Reveal>
              <Reveal variant="scale" delay={400} className="shrink-0">
                <Mascot stage="bloom" mood="excited" size={200} />
              </Reveal>
            </div>
          </div>
        </section>
      </Reveal>

      {/* ════════ JOURNEY — staggered cards ════════ */}
      <section className="py-28 sm:py-36 bg-[#fdf7ef]">
        <div className="max-w-5xl mx-auto px-6 sm:px-8">
          <Reveal>
            <div className="text-center mb-20">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/50 mb-3">YOUR JOURNEY</div>
              <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22]">你的蛻變旅程</h2>
            </div>
          </Reveal>
          <Stagger gap={150} className="space-y-6">
            {[
              { step: '01', Icon: Icons.Leaf, title: '遇見', desc: '第一次認識婕樂纖，對健康有了新的想像。一顆小小的種子，悄悄種在心裡。', color: 'from-[#e8f5e9] to-[#c8e6c9]', iconColor: 'text-[#4caf50]' },
              { step: '02', Icon: Icons.Sparkles, title: '探索', desc: '了解三階梯定價，找到最適合自己的組合搭配。根扎得更深，葉子開始伸展。', color: 'from-white to-[#fdf7ef]', iconColor: 'text-[#9F6B3E]' },
              { step: '03', Icon: Icons.Heart, title: '蛻變', desc: '營養師陪伴，堅持讓改變發生。一天一天，你開始看見自己的不同。', color: 'from-[#fce4ec] to-[#f8bbd0]', iconColor: 'text-[#e91e63]' },
              { step: '04', Icon: Icons.Star, title: '綻放', desc: '成為自信的潘朵拉。花開了，你把這份美好分享給身邊的人。', color: 'from-[#fff8e1] to-[#ffecb3]', iconColor: 'text-[#ff8f00]' },
            ].map((s) => (
              <StaggerChild key={s.step}>
                <div className={`flex flex-col sm:flex-row items-center gap-8 bg-gradient-to-br ${s.color} rounded-[2rem] p-8 sm:p-12 border border-white/60`}>
                  <div className="flex items-center gap-5 shrink-0">
                    <span className="text-[56px] sm:text-[72px] font-black leading-none text-black/[0.03]">{s.step}</span>
                    <div className={`w-16 h-16 rounded-2xl bg-white/80 flex items-center justify-center ${s.iconColor} shadow-sm`}>
                      <s.Icon className="w-8 h-8" />
                    </div>
                  </div>
                  <div>
                    <h3 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">{s.title}</h3>
                    <p className="text-sm sm:text-base text-gray-600 mt-3 leading-relaxed">{s.desc}</p>
                  </div>
                </div>
              </StaggerChild>
            ))}
          </Stagger>
        </div>
      </section>

      {/* ════════ TEAM — clip-path reveal ════════ */}
      <Reveal variant="clip">
        <section className="py-28 sm:py-36 bg-white">
          <div className="max-w-5xl mx-auto px-6 sm:px-8">
            <Reveal>
              <div className="text-center mb-20">
                <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/50 mb-3">WHO WE ARE</div>
                <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
              </div>
            </Reveal>
            <Stagger gap={200} className="grid md:grid-cols-3 gap-8">
              {[
                { Icon: Icons.Seedling, iconColor: 'text-[#9F6B3E]', bg: 'from-[#fdf7ef] to-[#f7eee3]', border: 'border-[#e7d9cb]', badge: 'CO-FOUNDER', badgeColor: 'text-[#9F6B3E]', name: '朵朵', creds: ['健康食品業 8 年', '台灣電商創業家'], bio: '從自己體驗開始，用「從仙女變成 Pandora」的精神，把好東西帶給更多女性。' },
                { Icon: Icons.Leaf, iconColor: 'text-[#2e7d32]', bg: 'from-[#e8f5e9] to-[#f1f8e9]', border: 'border-[#c8e6c9]', badge: 'NUTRITION TEAM', badgeColor: 'text-[#2e7d32]', name: '營養師顧問團', creds: ['專技高考合格營養師', '食品科學碩士'], bio: '纖體系列滿額即可加入陪伴班或陪跑班，享有專屬飲食指導與持續追蹤。' },
                { Icon: Icons.Gift, iconColor: 'text-[#1565c0]', bg: 'from-[#e3f2fd] to-[#bbdefb]', border: 'border-[#90caf9]', badge: 'CUSTOMER CARE', badgeColor: 'text-[#1565c0]', name: '客服仙女', creds: ['1 對 1 LINE 諮詢', '週一至週五上線'], bio: '從下單到售後、產品諮詢全包辦。平均 1 小時內回覆訊息。' },
              ].map((m) => (
                <StaggerChild key={m.name}>
                  <div className={`bg-gradient-to-br ${m.bg} rounded-[2rem] p-8 sm:p-10 ${m.border} border text-center h-full`}>
                    <div className={`w-20 h-20 mx-auto rounded-full bg-white/80 flex items-center justify-center ${m.iconColor} shadow-lg mb-6`}>
                      <m.Icon className="w-10 h-10" />
                    </div>
                    <div className={`text-[9px] font-black tracking-[0.25em] ${m.badgeColor} mb-1`}>{m.badge}</div>
                    <h3 className="text-2xl font-black text-[#3d2e22]">{m.name}</h3>
                    <div className="flex flex-wrap justify-center gap-1.5 mt-3">
                      {m.creds.map((c) => (
                        <span key={c} className="px-2.5 py-1 rounded-full bg-white/80 text-[10px] font-bold text-gray-500">{c}</span>
                      ))}
                    </div>
                    <p className="text-sm text-gray-500 mt-5 leading-relaxed">{m.bio}</p>
                  </div>
                </StaggerChild>
              ))}
            </Stagger>
          </div>
        </section>
      </Reveal>

      {/* ════════ VALUES — scale reveal cards ════════ */}
      <section className="py-28 sm:py-36" style={{ background: 'linear-gradient(180deg, #ffffff 0%, #fdf7ef 100%)' }}>
        <div className="max-w-4xl mx-auto px-6 sm:px-8">
          <Reveal>
            <div className="text-center mb-20">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/50 mb-3">OUR VALUES</div>
              <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22]">我們相信</h2>
            </div>
          </Reveal>
          <Stagger gap={150} className="grid sm:grid-cols-3 gap-8">
            {[
              { Icon: Icons.Shield, color: 'text-[#9F6B3E]', title: '正品堅持', desc: 'JEROSSE 官方授權經銷，每一件都是原廠出貨。' },
              { Icon: Icons.Heart, color: 'text-[#e91e63]', title: '真心陪伴', desc: '不催促、不話術。陪伴不是壓力。' },
              { Icon: Icons.Star, color: 'text-[#ff8f00]', title: '長期價值', desc: '三階梯定價買越多越划算，營養師陪伴讓改變持續。' },
            ].map((v) => (
              <StaggerChild key={v.title}>
                <div className="bg-white rounded-[2rem] p-8 sm:p-10 border border-[#e7d9cb] h-full hover:shadow-2xl hover:-translate-y-2 transition-all duration-700">
                  <div className={`w-14 h-14 rounded-xl bg-gray-50 flex items-center justify-center ${v.color} mb-5`}>
                    <v.Icon className="w-7 h-7" />
                  </div>
                  <h3 className="text-xl font-black text-[#3d2e22]">{v.title}</h3>
                  <p className="text-sm text-gray-500 mt-3 leading-relaxed">{v.desc}</p>
                </div>
              </StaggerChild>
            ))}
          </Stagger>
        </div>
      </section>

      {/* ════════ CTA — mix-blend-difference ════════ */}
      <Reveal variant="clip">
        <section className="relative py-32 sm:py-40 overflow-hidden" style={{
          background: 'linear-gradient(135deg, #9F6B3E 0%, #6b4424 50%, #3d2e22 100%)',
        }}>
          {/* Decorative blurs */}
          <div className="absolute top-0 left-[10%] w-[40vw] h-[40vw] rounded-full bg-[#f7c79a]/10 blur-[100px] pointer-events-none" />
          <div className="absolute bottom-0 right-[10%] w-[30vw] h-[30vw] rounded-full bg-[#E0748C]/10 blur-[80px] pointer-events-none" />

          <div className="relative max-w-3xl mx-auto px-6 sm:px-8 text-center">
            <Reveal variant="scale">
              <div className="mb-8">
                <Mascot stage="bloom" mood="excited" size={140} />
              </div>
            </Reveal>
            <Reveal delay={200}>
              <h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-white leading-[1.05] tracking-tight" style={{ mixBlendMode: 'difference' }}>
                準備好打開你的
                <br />潘朵拉盒子了嗎？
              </h2>
            </Reveal>
            <Reveal delay={400}>
              <p className="text-sm sm:text-base text-white/50 mt-6 max-w-md mx-auto leading-relaxed">
                每個蛻變都從一小步開始。無論你是第一次認識婕樂纖，或是想找到更適合自己的組合 — 我們都在這裡。
              </p>
            </Reveal>
            <Reveal delay={600}>
              <div className="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
                <Link href="/products" className="px-10 py-4 bg-white text-[#9F6B3E] font-black rounded-full hover:bg-white/90 transition-all duration-500 shadow-2xl min-h-[56px] flex items-center justify-center text-base">
                  開始選購
                </Link>
                <Link href="/articles" className="px-10 py-4 bg-white/10 backdrop-blur text-white font-black rounded-full hover:bg-white/20 transition-all duration-500 border border-white/15 min-h-[56px] flex items-center justify-center text-base">
                  閱讀仙女誌 →
                </Link>
              </div>
            </Reveal>
          </div>
        </section>
      </Reveal>
    </div>
  );
}
