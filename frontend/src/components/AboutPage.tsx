'use client';

/**
 * /about — immersive fullscreen SVG growth + concise copy.
 *
 * Design: large botanical SVG visuals (leaves, stems, petals) woven
 * between text sections. Sometimes visual left + text right, sometimes
 * reversed. Each visual is BIG (50-70vw) and parallax-driven.
 * Content is SHORT — one headline + one sentence per section max.
 */

import { useEffect, useRef } from 'react';
import Link from 'next/link';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Mascot from './Mascot';
import Icons from './SvgIcons';

gsap.registerPlugin(ScrollTrigger);

/* ── Large botanical SVG illustrations ── */
function LeafCluster({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 200 300" className={className} fill="none">
      <path d="M100 280 Q98 200 100 120" stroke="#7ab87a" strokeWidth="3" strokeLinecap="round" />
      <path d="M100 220 Q60 200 30 160" stroke="#7ab87a" strokeWidth="2" strokeLinecap="round" />
      <path d="M100 220 Q140 200 170 160" stroke="#7ab87a" strokeWidth="2" strokeLinecap="round" />
      <ellipse cx="40" cy="165" rx="35" ry="20" fill="#8ccf8c" opacity="0.8" transform="rotate(-30 40 165)" />
      <ellipse cx="160" cy="165" rx="35" ry="20" fill="#a7e3a7" opacity="0.7" transform="rotate(30 160 165)" />
      <ellipse cx="55" cy="200" rx="25" ry="14" fill="#6faa6f" opacity="0.5" transform="rotate(-20 55 200)" />
      <ellipse cx="145" cy="200" rx="25" ry="14" fill="#8ccf8c" opacity="0.5" transform="rotate(20 145 200)" />
      <path d="M100 180 Q80 170 65 140" stroke="#6faa6f" strokeWidth="1.5" strokeLinecap="round" />
      <path d="M100 180 Q120 170 135 140" stroke="#6faa6f" strokeWidth="1.5" strokeLinecap="round" />
    </svg>
  );
}

function BloomFlower({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 200 200" className={className} fill="none">
      <defs>
        <linearGradient id="abPetal" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="#ffecf1" />
          <stop offset="100%" stopColor="#ff8fa8" />
        </linearGradient>
        <linearGradient id="abPetal2" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#ffe0e8" />
          <stop offset="100%" stopColor="#ffb3c6" />
        </linearGradient>
      </defs>
      {[0, 72, 144, 216, 288].map((deg, i) => (
        <g key={deg} transform={`rotate(${deg} 100 100)`}>
          <ellipse cx="100" cy="55" rx="22" ry="16" fill={i % 2 === 0 ? 'url(#abPetal)' : 'url(#abPetal2)'} />
        </g>
      ))}
      <circle cx="100" cy="100" r="12" fill="#fff176" />
      <circle cx="100" cy="100" r="8" fill="#ffee58" />
      {[0, 60, 120, 180, 240, 300].map((a) => {
        const x = 100 + Math.cos((a * Math.PI) / 180) * 7;
        const y = 100 + Math.sin((a * Math.PI) / 180) * 7;
        return <circle key={a} cx={x} cy={y} r="2" fill="#f9a825" />;
      })}
    </svg>
  );
}

function SeedSvg({ className = '' }: { className?: string }) {
  return (
    <svg viewBox="0 0 100 100" className={className} fill="none">
      <ellipse cx="50" cy="70" rx="18" ry="25" fill="#8B6914" />
      <ellipse cx="50" cy="65" rx="13" ry="18" fill="#A67C00" />
      <path d="M50 50 Q48 40 50 30" stroke="#7ab87a" strokeWidth="2" strokeLinecap="round" opacity="0.6" />
      <ellipse cx="44" cy="35" rx="6" ry="3" fill="#8ccf8c" opacity="0.5" transform="rotate(-25 44 35)" />
      <ellipse cx="56" cy="35" rx="6" ry="3" fill="#8ccf8c" opacity="0.5" transform="rotate(25 56 35)" />
    </svg>
  );
}

export default function AboutPage() {
  const main = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const ctx = gsap.context(() => {
      // Parallax
      gsap.utils.toArray<HTMLElement>('[data-speed]').forEach((el) => {
        const speed = parseFloat(el.dataset.speed || '1');
        gsap.to(el, {
          y: () => (1 - speed) * 500,
          ease: 'none',
          scrollTrigger: { trigger: el.closest('section') || el, start: 'top bottom', end: 'bottom top', scrub: 1.5 },
        });
      });

      // Line reveals
      gsap.utils.toArray<HTMLElement>('.gs-lines').forEach((el) => {
        const lines = el.querySelectorAll('.gs-line');
        gsap.fromTo(lines,
          { y: 100, opacity: 0, rotateX: 35 },
          { y: 0, opacity: 1, rotateX: 0, stagger: 0.1, ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 82%', end: 'top 32%', scrub: 1.2 } },
        );
      });

      // Fade reveals
      gsap.utils.toArray<HTMLElement>('.gs-reveal').forEach((el) => {
        gsap.fromTo(el,
          { y: 120, opacity: 0, filter: 'blur(6px)' },
          { y: 0, opacity: 1, filter: 'blur(0px)', ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 88%', end: 'top 38%', scrub: 1.2 } },
        );
      });

      // Scale reveals (SVG visuals)
      gsap.utils.toArray<HTMLElement>('.gs-grow').forEach((el) => {
        gsap.fromTo(el,
          { scale: 0.3, opacity: 0, filter: 'blur(12px)', rotation: -10 },
          { scale: 1, opacity: 1, filter: 'blur(0px)', rotation: 0, ease: 'power2.out',
            scrollTrigger: { trigger: el, start: 'top 90%', end: 'top 25%', scrub: 1.8 } },
        );
      });

      // Slide from sides
      gsap.utils.toArray<HTMLElement>('.gs-left').forEach((el) => {
        gsap.fromTo(el, { x: -150, opacity: 0 },
          { x: 0, opacity: 1, ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 85%', end: 'top 35%', scrub: 1.2 } });
      });
      gsap.utils.toArray<HTMLElement>('.gs-right').forEach((el) => {
        gsap.fromTo(el, { x: 150, opacity: 0 },
          { x: 0, opacity: 1, ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 85%', end: 'top 35%', scrub: 1.2 } });
      });

      // Counters
      gsap.utils.toArray<HTMLElement>('.gs-counter').forEach((el) => {
        const target = parseInt(el.dataset.target || '0', 10);
        const obj = { val: 0 };
        gsap.to(obj, {
          val: target, duration: 1.5, ease: 'power2.out',
          scrollTrigger: { trigger: el, start: 'top 80%', toggleActions: 'play none none none' },
          onUpdate: () => { el.textContent = Math.round(obj.val).toLocaleString(); },
        });
      });

      // Stagger
      gsap.utils.toArray<HTMLElement>('.gs-stagger').forEach((container) => {
        const children = container.querySelectorAll('.gs-si');
        gsap.fromTo(children,
          { y: 80, opacity: 0, filter: 'blur(4px)' },
          { y: 0, opacity: 1, filter: 'blur(0px)', stagger: 0.15, ease: 'power3.out',
            scrollTrigger: { trigger: container, start: 'top 78%', end: 'top 22%', scrub: 1.5 } },
        );
      });

    }, main);
    return () => ctx.revert();
  }, []);

  return (
    <div ref={main} className="overflow-hidden">

      {/* ═══ HERO ═══ */}
      <section className="relative min-h-screen flex items-center justify-center" style={{
        background: 'linear-gradient(180deg, #1a1410 0%, #3d2e22 100%)',
      }}>
        <div className="absolute inset-0 flex items-center justify-center pointer-events-none" aria-hidden>
          <div className="font-black text-[32vw] leading-none tracking-tighter opacity-[0.025]" data-speed="0.3" style={{ color: '#e7d9cb' }}>FP</div>
        </div>
        {/* Seed visual — large, right side */}
        <div className="absolute right-[5%] sm:right-[10%] top-1/2 -translate-y-1/2 gs-grow" data-speed="0.5">
          <SeedSvg className="w-[35vw] sm:w-[25vw] max-w-[300px] opacity-20" />
        </div>
        <div className="relative text-center px-6 py-20 max-w-3xl mx-auto z-10">
          <div className="gs-lines overflow-hidden">
            <div className="gs-line"><h1 className="text-5xl sm:text-7xl lg:text-8xl font-black text-[#f7eee3] leading-[1.05] tracking-tight">從仙女</h1></div>
            <div className="gs-line"><h1 className="text-5xl sm:text-7xl lg:text-8xl font-black text-[#f7c79a] leading-[1.05] tracking-tight">到潘朵拉</h1></div>
          </div>
          <p className="gs-reveal text-base sm:text-lg text-[#e7d9cb]/35 mt-8 max-w-md mx-auto">
            FP 的使命，是陪你打開那個專屬的盒子。
          </p>
        </div>
      </section>

      {/* ═══ FAIRY — visual LEFT, text RIGHT ═══ */}
      <section className="relative min-h-[80vh] flex items-center" style={{
        background: 'linear-gradient(180deg, #1e3a1e 0%, #2a4a2a 100%)',
      }}>
        {/* Large leaf cluster — left side, 50vw */}
        <div className="absolute left-[-5%] top-1/2 -translate-y-1/2 gs-grow" data-speed="0.4">
          <LeafCluster className="w-[50vw] sm:w-[40vw] max-w-[500px] opacity-30" />
        </div>
        <div className="relative max-w-5xl mx-auto px-6 sm:px-8 w-full z-10">
          <div className="ml-auto max-w-lg">
            <div className="gs-lines overflow-hidden">
              <div className="gs-line"><h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-[#f7eee3] leading-[1.08]">每個女生</h2></div>
              <div className="gs-line"><h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-[#8ccf8c] leading-[1.08]">都是仙女</h2></div>
            </div>
            <p className="gs-reveal text-sm sm:text-base text-[#e7d9cb]/30 mt-6 leading-relaxed">
              只是有時候忘了。婕樂纖是那個提醒你的契機。
            </p>
          </div>
        </div>
      </section>

      {/* ═══ PANDORA — visual RIGHT, text LEFT ═══ */}
      <section className="relative min-h-[80vh] flex items-center" style={{
        background: 'linear-gradient(180deg, #fdf7ef 0%, #f7eee3 100%)',
      }}>
        {/* Large bloom flower — right side */}
        <div className="absolute right-[-5%] top-1/2 -translate-y-1/2 gs-grow" data-speed="0.4">
          <BloomFlower className="w-[50vw] sm:w-[40vw] max-w-[500px] opacity-25" />
        </div>
        <div className="relative max-w-5xl mx-auto px-6 sm:px-8 w-full z-10">
          <div className="max-w-lg">
            <div className="gs-lines overflow-hidden">
              <div className="gs-line"><h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-[#3d2e22] leading-[1.08]">盒子裡是</h2></div>
              <div className="gs-line"><h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-[#9F6B3E] leading-[1.08]">希望</h2></div>
            </div>
            <p className="gs-reveal text-sm sm:text-base text-[#3d2e22]/30 mt-6 leading-relaxed">
              打開它需要勇氣。我們陪你一起。
            </p>
          </div>
        </div>
      </section>

      {/* ═══ NUMBERS — centered, compact ═══ */}
      <section className="py-20 sm:py-28 bg-[#3d2e22]">
        <div className="max-w-4xl mx-auto px-6 sm:px-8 gs-stagger grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
          {[
            { target: 249, suffix: '+', label: '品質大獎' },
            { target: 25, suffix: '座', label: '玉山獎' },
            { target: 8, suffix: '年', label: '品牌經營' },
            { target: 100, suffix: '%', label: '正品授權' },
          ].map((n) => (
            <div key={n.label} className="gs-si">
              <div className="flex items-baseline justify-center gap-1">
                <span className="gs-counter text-4xl sm:text-5xl font-black text-[#f7c79a]" data-target={n.target}>0</span>
                <span className="text-lg font-black text-[#f7c79a]/50">{n.suffix}</span>
              </div>
              <div className="text-[10px] font-bold text-[#e7d9cb]/30 mt-2 tracking-wider">{n.label}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ═══ TEAM — full-width alternating layout ═══ */}
      <section className="relative py-32 sm:py-44 bg-white overflow-hidden">
        <div className="max-w-6xl mx-auto px-6 sm:px-8">
          <div className="gs-reveal text-center mb-20">
            <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E]/40 mb-3">WHO WE ARE</div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
          </div>

          {/* Each team member: alternating left/right, large visual + text */}
          <div className="space-y-24 sm:space-y-32">
            {/* 朵朵 — visual left */}
            <div className="flex flex-col md:flex-row items-center gap-10 md:gap-16">
              <div className="gs-left flex-1 flex justify-center">
                <div className="relative">
                  <div className="w-48 h-48 sm:w-64 sm:h-64 rounded-[3rem] bg-gradient-to-br from-[#fdf7ef] to-[#e7d9cb] flex items-center justify-center" data-speed="0.6">
                    <Icons.Seedling className="w-24 h-24 sm:w-32 sm:h-32 text-[#9F6B3E]/30" />
                  </div>
                  <div className="absolute -bottom-4 -right-4 w-16 h-16 rounded-2xl bg-[#9F6B3E] flex items-center justify-center gs-scale">
                    <Icons.Seedling className="w-8 h-8 text-white" />
                  </div>
                </div>
              </div>
              <div className="flex-1">
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]/50 mb-3">CO-FOUNDER</div>
                </div>
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line"><h3 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">朵朵</h3></div>
                </div>
                <p className="gs-reveal text-sm sm:text-base text-gray-500 mt-4 leading-relaxed max-w-md">
                  用「從仙女變成 Pandora」的精神，把好東西帶給更多女性。健康食品業 8 年，台灣電商創業家。
                </p>
              </div>
            </div>

            {/* 營養師 — visual right */}
            <div className="flex flex-col md:flex-row-reverse items-center gap-10 md:gap-16">
              <div className="gs-right flex-1 flex justify-center">
                <div className="relative">
                  <div className="w-48 h-48 sm:w-64 sm:h-64 rounded-[3rem] bg-gradient-to-br from-[#e8f5e9] to-[#c8e6c9] flex items-center justify-center" data-speed="0.6">
                    <Icons.Leaf className="w-24 h-24 sm:w-32 sm:h-32 text-[#2e7d32]/25" />
                  </div>
                  <div className="absolute -bottom-4 -left-4 w-16 h-16 rounded-2xl bg-[#2e7d32] flex items-center justify-center gs-scale">
                    <Icons.Leaf className="w-8 h-8 text-white" />
                  </div>
                </div>
              </div>
              <div className="flex-1">
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line text-[10px] font-black tracking-[0.3em] text-[#2e7d32]/50 mb-3">NUTRITION TEAM</div>
                </div>
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line"><h3 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">營養師顧問團</h3></div>
                </div>
                <p className="gs-reveal text-sm sm:text-base text-gray-500 mt-4 leading-relaxed max-w-md">
                  專技高考合格營養師。纖體系列滿額即可加入陪伴班或陪跑班，專屬飲食指導與持續追蹤。
                </p>
              </div>
            </div>

            {/* 客服 — visual left */}
            <div className="flex flex-col md:flex-row items-center gap-10 md:gap-16">
              <div className="gs-left flex-1 flex justify-center">
                <div className="relative">
                  <div className="w-48 h-48 sm:w-64 sm:h-64 rounded-[3rem] bg-gradient-to-br from-[#e3f2fd] to-[#bbdefb] flex items-center justify-center" data-speed="0.6">
                    <Icons.Gift className="w-24 h-24 sm:w-32 sm:h-32 text-[#1565c0]/25" />
                  </div>
                  <div className="absolute -bottom-4 -right-4 w-16 h-16 rounded-2xl bg-[#1565c0] flex items-center justify-center gs-scale">
                    <Icons.Gift className="w-8 h-8 text-white" />
                  </div>
                </div>
              </div>
              <div className="flex-1">
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line text-[10px] font-black tracking-[0.3em] text-[#1565c0]/50 mb-3">CUSTOMER CARE</div>
                </div>
                <div className="gs-lines overflow-hidden">
                  <div className="gs-line"><h3 className="text-3xl sm:text-4xl font-black text-[#3d2e22]">客服仙女</h3></div>
                </div>
                <p className="gs-reveal text-sm sm:text-base text-gray-500 mt-4 leading-relaxed max-w-md">
                  1 對 1 LINE 諮詢，週一至週五上線。從下單到售後全包辦，平均 1 小時內回覆。
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ═══ VALUES — horizontal scroll-like full-width cards ═══ */}
      <section className="relative py-32 sm:py-44 overflow-hidden" style={{ background: 'linear-gradient(180deg, #fdf7ef 0%, #f7eee3 100%)' }}>
        {/* Decorative large leaf */}
        <div className="absolute right-[-10%] top-[20%] gs-grow opacity-10" data-speed="0.4">
          <LeafCluster className="w-[30vw] max-w-[300px]" />
        </div>
        <div className="max-w-6xl mx-auto px-6 sm:px-8">
          <div className="gs-reveal text-center mb-20">
            <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E]/40 mb-3">OUR VALUES</div>
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22]">我們相信</h2>
          </div>
          <div className="space-y-6">
            {[
              { Icon: Icons.Shield, color: 'text-[#9F6B3E]', bg: 'from-[#fdf7ef] to-white', title: '正品堅持', desc: 'JEROSSE 官方授權經銷。每一件都是原廠出貨，你買到的跟品牌官網完全一樣。', dir: 'gs-left' },
              { Icon: Icons.Heart, color: 'text-[#e91e63]', bg: 'from-[#fce4ec] to-white', title: '真心陪伴', desc: '不催促、不話術。你想了解，我就說；你想等等，我就等。陪伴不是壓力。', dir: 'gs-right' },
              { Icon: Icons.Star, color: 'text-[#ff8f00]', bg: 'from-[#fff8e1] to-white', title: '長期價值', desc: '三階梯定價讓你買越多越划算。營養師陪伴讓改變可以持續，不是曇花一現。', dir: 'gs-left' },
            ].map((v) => (
              <div key={v.title} className={v.dir}>
                <div className={`bg-gradient-to-r ${v.bg} rounded-[2rem] p-8 sm:p-12 border border-white/60 flex flex-col sm:flex-row items-center gap-6 sm:gap-10`}>
                  <div className={`w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-white flex items-center justify-center ${v.color} shadow-lg shrink-0`}>
                    <v.Icon className="w-10 h-10 sm:w-12 sm:h-12" />
                  </div>
                  <div>
                    <h3 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">{v.title}</h3>
                    <p className="text-sm sm:text-base text-gray-500 mt-3 leading-relaxed">{v.desc}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ CTA — bloom visual + short copy ═══ */}
      <section className="relative py-32 sm:py-44 overflow-hidden" style={{
        background: 'linear-gradient(135deg, #9F6B3E 0%, #6b4424 50%, #3d2e22 100%)',
      }}>
        <div className="absolute top-0 left-[5%] w-[50vw] h-[50vw] rounded-full bg-[#f7c79a]/8 blur-[120px] pointer-events-none" data-speed="0.3" />
        <div className="absolute bottom-[-10%] right-[-5%] gs-grow" data-speed="0.4">
          <BloomFlower className="w-[45vw] max-w-[400px] opacity-10" />
        </div>
        <div className="relative max-w-3xl mx-auto px-6 sm:px-8 text-center z-10">
          <div className="gs-scale mb-8">
            <Mascot stage="bloom" mood="excited" size={140} />
          </div>
          <div className="gs-lines overflow-hidden">
            <div className="gs-line"><h2 className="text-3xl sm:text-5xl lg:text-6xl font-black text-white leading-[1.08]">準備好了嗎？</h2></div>
          </div>
          <div className="gs-reveal mt-10 flex flex-col sm:flex-row gap-4 justify-center">
            <Link href="/products" className="px-10 py-4 bg-white text-[#9F6B3E] font-black rounded-full hover:bg-white/90 transition-all duration-500 shadow-2xl min-h-[56px] flex items-center justify-center">
              開始選購
            </Link>
            <Link href="/articles" className="px-10 py-4 bg-white/10 backdrop-blur text-white font-black rounded-full hover:bg-white/20 transition-all duration-500 border border-white/15 min-h-[56px] flex items-center justify-center">
              閱讀仙女誌 →
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
