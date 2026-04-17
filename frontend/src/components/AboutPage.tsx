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

      {/* ═══ TEAM — 3 cards, concise ═══ */}
      <section className="py-28 sm:py-36 bg-white">
        <div className="max-w-5xl mx-auto px-6 sm:px-8">
          <div className="gs-reveal text-center mb-16">
            <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
          </div>
          <div className="gs-stagger grid md:grid-cols-3 gap-6">
            {[
              { Icon: Icons.Seedling, iconColor: 'text-[#9F6B3E]', bg: 'from-[#fdf7ef] to-[#f7eee3]', border: 'border-[#e7d9cb]', name: '朵朵', role: 'Co-Founder', bio: '從仙女變成 Pandora 的精神推動者。' },
              { Icon: Icons.Leaf, iconColor: 'text-[#2e7d32]', bg: 'from-[#e8f5e9] to-[#f1f8e9]', border: 'border-[#c8e6c9]', name: '營養師團', role: 'Nutrition', bio: '滿額加入陪伴班或陪跑班。' },
              { Icon: Icons.Gift, iconColor: 'text-[#1565c0]', bg: 'from-[#e3f2fd] to-[#bbdefb]', border: 'border-[#90caf9]', name: '客服仙女', role: 'Care', bio: '1 對 1 LINE 諮詢，1 小時內回覆。' },
            ].map((m) => (
              <div key={m.name} className="gs-si">
                <div className={`bg-gradient-to-br ${m.bg} rounded-[2rem] p-8 ${m.border} border text-center h-full`}>
                  <div className={`w-16 h-16 mx-auto rounded-full bg-white/80 flex items-center justify-center ${m.iconColor} shadow-lg mb-4`}>
                    <m.Icon className="w-8 h-8" />
                  </div>
                  <div className="text-[9px] font-black tracking-[0.2em] text-gray-400 mb-1">{m.role.toUpperCase()}</div>
                  <h3 className="text-xl font-black text-[#3d2e22]">{m.name}</h3>
                  <p className="text-sm text-gray-500 mt-3">{m.bio}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ VALUES — 3 compact cards ═══ */}
      <section className="py-28 sm:py-36" style={{ background: 'linear-gradient(180deg, #fff 0%, #fdf7ef 100%)' }}>
        <div className="max-w-4xl mx-auto px-6 sm:px-8">
          <div className="gs-reveal text-center mb-16">
            <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">我們相信</h2>
          </div>
          <div className="gs-stagger grid sm:grid-cols-3 gap-6">
            {[
              { Icon: Icons.Shield, color: 'text-[#9F6B3E]', title: '正品堅持', desc: '官方授權，原廠出貨。' },
              { Icon: Icons.Heart, color: 'text-[#e91e63]', title: '真心陪伴', desc: '不催促、不話術。' },
              { Icon: Icons.Star, color: 'text-[#ff8f00]', title: '長期價值', desc: '三階梯 + 營養師，持續改變。' },
            ].map((v) => (
              <div key={v.title} className="gs-si">
                <div className="bg-white rounded-[2rem] p-8 border border-[#e7d9cb] h-full hover:shadow-2xl hover:-translate-y-2 transition-all duration-700">
                  <div className={`w-12 h-12 rounded-xl bg-gray-50 flex items-center justify-center ${v.color} mb-4`}>
                    <v.Icon className="w-6 h-6" />
                  </div>
                  <h3 className="text-lg font-black text-[#3d2e22]">{v.title}</h3>
                  <p className="text-sm text-gray-500 mt-2">{v.desc}</p>
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
