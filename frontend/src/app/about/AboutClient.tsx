'use client';

import { useEffect, useRef, useCallback } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import gsap from 'gsap';
import Mascot from '@/components/Mascot';
import GsapScrollInit from '@/components/GsapScrollInit';

export default function AboutPage() {
  const heroRef = useRef<HTMLDivElement>(null);

  // Entrance animation — fires immediately, no scroll needed
  useEffect(() => {
    const hero = heroRef.current;
    if (!hero) return;
    const els = hero.querySelectorAll('.hero-enter');
    gsap.set(els, { opacity: 0, y: 40, filter: 'blur(6px)' });
    gsap.to(els, {
      opacity: 1, y: 0, filter: 'blur(0px)',
      duration: 1.2, ease: 'power3.out',
      stagger: 0.2, delay: 0.15,
    });
  }, []);

  return (
    <div className="about-premium bg-[#0d0b09]">
      <GsapScrollInit />
      {/* Zen Maru Gothic — 日系圓角字體 + Outfit — 英文幾何圓角 */}
      {/* eslint-disable-next-line @next/next/no-page-custom-font */}
      <link href="https://fonts.googleapis.com/css2?family=Zen+Maru+Gothic:wght@300;400;500;700;900&family=Outfit:wght@300;400;600;700;800;900&display=swap" rel="stylesheet" />

      <style jsx global>{`
        .about-premium {
          --gold: #c9a84c;
          --gold-light: #e8d48b;
          --gold-bright: #f5e6a3;
          --gold-dim: #8a7340;
          --dark: #0d0b09;
          --dark-card: #151210;
          font-feature-settings: 'palt' 1;
          color: #fff;
        }
        /* 內文段落 + 引言：日系圓角字體 */
        .about-premium p,
        .about-premium blockquote {
          font-family: 'Zen Maru Gothic', -apple-system, 'PingFang TC', 'Noto Sans TC', sans-serif;
        }
        /* 英文標題：幾何圓角無襯線 */
        .fp-en {
          font-family: 'Outfit', -apple-system, sans-serif;
        }
        .about-premium section { overflow: hidden; position: relative; }

        /* ── Hero display ── */
        .fp-hero {
          font-weight: 900;
          letter-spacing: 0.3em;
          line-height: 0.85;
        }
        .fp-hero-sub {
          font-weight: 700;
          letter-spacing: 0.5em;
        }

        /* ── Section headings ── */
        .fp-heading {
          font-weight: 700;
          letter-spacing: 0.08em;
        }

        /* ── Scan line overlay ── */
        .scan-lines::before {
          content: '';
          position: absolute; inset: 0;
          background: repeating-linear-gradient(
            0deg,
            transparent,
            transparent 2px,
            rgba(201,168,76,0.025) 2px,
            rgba(201,168,76,0.025) 4px
          );
          pointer-events: none;
          z-index: 1;
        }

        /* ── Flowing gold lines canvas ── */
        .flow-canvas {
          position: absolute; inset: 0;
          pointer-events: none;
          z-index: 0;
        }

        /* ── Ripple rings — more visible ── */
        @keyframes rippleOut {
          0% { transform: scale(0.2); opacity: 0.45; }
          100% { transform: scale(3.5); opacity: 0; }
        }
        .ripple-ring {
          position: absolute; border-radius: 50%;
          border: 1.5px solid var(--gold);
          animation: rippleOut 6s ease-out infinite;
        }

        /* ── Shimmer text ── */
        @keyframes shimmer {
          0% { background-position: -200% center; }
          100% { background-position: 200% center; }
        }
        .text-shimmer {
          background: linear-gradient(
            110deg,
            var(--gold-dim) 0%,
            var(--gold) 20%,
            var(--gold-light) 35%,
            #fffef5 50%,
            var(--gold-light) 65%,
            var(--gold) 80%,
            var(--gold-dim) 100%
          );
          background-size: 250% auto;
          -webkit-background-clip: text; background-clip: text;
          -webkit-text-fill-color: transparent;
          animation: shimmer 5s ease-in-out infinite;
        }

        /* ── Hero FP glow ── */
        .fp-glow {
          text-shadow: 0 0 100px rgba(201,168,76,0.4), 0 0 200px rgba(201,168,76,0.15);
        }

        /* ── Fairy sparkle animations ── */
        @keyframes fairySparkle1 {
          0%, 100% { opacity: 0.2; transform: scale(0.6); }
          50% { opacity: 1; transform: scale(1.3); }
        }
        @keyframes fairySparkle2 {
          0%, 100% { opacity: 0.1; transform: scale(0.5) rotate(0deg); }
          50% { opacity: 0.9; transform: scale(1.2) rotate(20deg); }
        }
        @keyframes fairyGlow {
          0%, 100% { opacity: 0.2; r: 3; }
          50% { opacity: 0.6; r: 5; }
        }
        .fairy-spark-1 { transform-origin: center; transform-box: fill-box; animation: fairySparkle1 2s ease-in-out infinite; }
        .fairy-spark-2 { transform-origin: center; transform-box: fill-box; animation: fairySparkle2 2.5s ease-in-out infinite 0.5s; }
        .fairy-spark-3 { transform-origin: center; transform-box: fill-box; animation: fairySparkle1 3s ease-in-out infinite 1s; }
        .fairy-spark-4 { transform-origin: center; transform-box: fill-box; animation: fairySparkle2 2.2s ease-in-out infinite 1.5s; }
        .fairy-tip-glow { animation: fairyGlow 2s ease-in-out infinite; }

        /* ── Pandora box animations ── */
        @keyframes boxGlow {
          0%, 100% { opacity: 0.15; }
          50% { opacity: 0.6; }
        }
        @keyframes boxGlowBig {
          0%, 100% { opacity: 0.05; r: 10; }
          50% { opacity: 0.25; r: 16; }
        }
        @keyframes boxRay {
          0%, 100% { opacity: 0.1; transform: scaleY(0.7); }
          50% { opacity: 0.7; transform: scaleY(1.15); }
        }
        @keyframes boxLidL {
          0%, 100% { transform: rotate(0deg); }
          50% { transform: rotate(-18deg); }
        }
        @keyframes boxLidR {
          0%, 100% { transform: rotate(0deg); }
          50% { transform: rotate(18deg); }
        }
        @keyframes boxParticle {
          0% { opacity: 0; transform: translateY(0) scale(0.5); }
          20% { opacity: 1; }
          100% { opacity: 0; transform: translateY(-20px) scale(0); }
        }
        .box-glow { animation: boxGlow 3s ease-in-out infinite; }
        .box-glow-big { animation: boxGlowBig 3s ease-in-out infinite; }
        .box-ray { transform-origin: 28px 22px; }
        .box-ray-1 { animation: boxRay 2.5s ease-in-out infinite; }
        .box-ray-2 { animation: boxRay 2.5s ease-in-out infinite 0.35s; }
        .box-ray-3 { animation: boxRay 2.5s ease-in-out infinite 0.7s; }
        .box-ray-4 { animation: boxRay 2.5s ease-in-out infinite 1.05s; }
        .box-ray-5 { animation: boxRay 2.5s ease-in-out infinite 1.4s; }
        .box-ray-6 { animation: boxRay 2.5s ease-in-out infinite 1.75s; }
        .box-ray-7 { animation: boxRay 2.5s ease-in-out infinite 2.1s; }
        .box-lid-l { transform-origin: 12px 28px; animation: boxLidL 4s ease-in-out infinite; }
        .box-lid-r { transform-origin: 44px 28px; animation: boxLidR 4s ease-in-out infinite; }
        .box-particle { animation: boxParticle 3s ease-out infinite; transform-origin: center; }
        .box-p1 { animation-delay: 0s; }
        .box-p2 { animation-delay: 0.5s; }
        .box-p3 { animation-delay: 1s; }
        .box-p4 { animation-delay: 1.5s; }
        .box-p5 { animation-delay: 2s; }
        .box-p6 { animation-delay: 2.5s; }

        /* ── Interactive tilt card ── */
        .tilt-card {
          transition: transform 0.15s ease-out, box-shadow 0.3s ease;
          transform-style: preserve-3d;
          will-change: transform;
        }
        .tilt-card:hover {
          box-shadow: 0 20px 60px rgba(201,168,76,0.12), 0 0 30px rgba(201,168,76,0.06);
        }

        /* ── Hover glow border ── */
        .glow-border {
          position: relative;
          transition: border-color 0.4s ease;
        }
        .glow-border::after {
          content: '';
          position: absolute; inset: -1px;
          border-radius: inherit;
          background: linear-gradient(135deg, var(--gold), transparent 40%, transparent 60%, var(--gold-dim));
          opacity: 0;
          transition: opacity 0.4s ease;
          z-index: -1;
          mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
          mask-composite: exclude;
          -webkit-mask-composite: xor;
          padding: 1px;
        }
        .glow-border:hover::after { opacity: 0.5; }

        /* ── Magnetic button ── */
        .mag-btn { transition: transform 0.2s ease-out; }

        /* ── Parallax mouse layer ── */
        .mouse-parallax { transition: transform 0.3s ease-out; }
      `}</style>

      {/* ═══════════════════════════════════════════
          1. HERO — FP 團隊
      ═══════════════════════════════════════════ */}
      <section ref={heroRef} className="relative min-h-screen flex items-center justify-center scan-lines">
        <FlowingGoldLines density={8} />
        <RippleBackground opacity={0.06} />
        <MouseParallax className="absolute inset-0 flex items-center justify-center pointer-events-none" factor={0.02}>
          <div className="w-[500px] h-[500px] rounded-full opacity-[0.06]" style={{
            background: 'radial-gradient(ellipse, var(--gold), transparent 65%)',
          }} />
        </MouseParallax>

        <div className="relative max-w-4xl mx-auto px-6 text-center z-10">
          <div className="hero-enter">
            <div className="w-px h-16 mx-auto mb-6" style={{ background: 'linear-gradient(to bottom, transparent, var(--gold), transparent)' }} />
            <span className="text-[11px] font-medium tracking-[0.6em] text-[var(--gold)] uppercase">Fairy Pandora · Est. 2019</span>
          </div>
          <div className="hero-enter mt-10">
            <h1 className="fp-glow">
              <span className="fp-en fp-hero block text-[clamp(6rem,20vw,14rem)] text-shimmer">
                FP
              </span>
              <span className="fp-en fp-hero-sub block text-[clamp(1rem,3vw,1.6rem)] text-[var(--gold)] mt-4">
                TEAM
              </span>
            </h1>
          </div>
          <div className="hero-enter mt-10">
            <p className="text-base sm:text-lg text-white/50 max-w-lg mx-auto leading-relaxed font-light">
              每個推薦的背後，都是一段真實的故事。<br />
              我們不是一間普通的電商 — 我們是一群親身體驗過改變的人。
            </p>
          </div>
          <div className="hero-enter mt-14">
            <div className="w-px h-20 mx-auto" style={{ background: 'linear-gradient(to bottom, var(--gold), transparent)' }} />
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          2. FOUNDER 朵朵
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-40" style={{ background: 'linear-gradient(180deg, var(--dark) 0%, #12100e 100%)' }}>
        <FlowingGoldLines density={4} />
        <div className="relative max-w-5xl mx-auto px-6 sm:px-8 z-10">
          <div className="gs-reveal text-center mb-20">
            <div className="h-px w-20 mx-auto mb-4" style={{ background: 'linear-gradient(90deg, transparent, var(--gold), transparent)' }} />
            <span className="text-[11px] font-medium tracking-[0.5em] text-[var(--gold)] uppercase">Founder & Team Leader</span>
          </div>

          <div className="flex flex-col lg:flex-row items-start gap-14 lg:gap-20">
            {/* Photo — interactive tilt */}
            <div className="gs-left w-full max-w-[400px] lg:max-w-[440px] shrink-0 lg:sticky lg:top-28">
              <TiltCard className="relative">
                <div className="aspect-[2/3] rounded-[20px] overflow-hidden border border-[var(--gold)]/15 relative">
                  <Image
                    src="/images/duoduo.jpg"
                    alt="朵朵 — FP 皇家團隊長"
                    fill
                    className="object-cover object-center"
                    sizes="(max-width: 1024px) 400px, 440px"
                    priority
                  />
                </div>
                <div className="absolute -bottom-3 -right-3 w-2/3 h-2/3 rounded-[20px] border border-[var(--gold)]/15 -z-10" />
                <div className="absolute -top-3 -left-3 w-1/3 h-1/3 rounded-[20px] border border-[var(--gold)]/10 -z-10" />
              </TiltCard>

              <div className="mt-8 flex flex-wrap gap-2 justify-center lg:justify-start">
                {['經營兩年升上最高階', '36歲素人二寶媽代表', 'FP 皇家團隊長'].map((c) => (
                  <span key={c} className="px-4 py-2 rounded-full border border-[var(--gold)]/25 text-[12px] font-bold text-[var(--gold)] tracking-wide">{c}</span>
                ))}
              </div>
            </div>

            {/* Story */}
            <div className="flex-1">
              <div className="gs-lines">
                <h2 className="gs-line text-4xl sm:text-5xl font-black tracking-tight text-shimmer inline-block">
                  朵朵
                </h2>
                <div className="gs-line mt-2">
                  <span className="text-sm font-bold text-[var(--gold)] tracking-wider">FP 皇家團隊長 · Co-Founder</span>
                </div>

                <blockquote className="gs-line mt-10 text-xl sm:text-2xl font-light italic leading-relaxed text-white/60 border-l-2 border-[var(--gold)]/50 pl-6">
                  「不是因為要賣東西才說好，<br />
                  是因為自己真的改變了，才想分享。」
                </blockquote>

                <div className="mt-12 space-y-6 text-[15px] text-white/55 leading-[2]">
                  <p className="gs-line">
                    兩年前，朵朵是一個 36 歲的二寶媽。每天在家庭和孩子之間忙碌，漸漸忘了自己也可以有夢想。直到體驗了婕樂纖帶來的改變 — 不只是外在的變化，更是<strong className="text-white/90">找回了對自己的信心</strong>。
                  </p>
                  <p className="gs-line">
                    身邊的朋友開始問：「你最近怎麼了？整個人都不一樣了。」一個問、兩個問、十個問⋯⋯朵朵發現，好東西真的會自己說話。更重要的是，她發現<strong className="text-white/90">幫助別人改變的成就感，比自己改變還要大</strong>。
                  </p>
                  <p className="gs-line">
                    從一個人分享，到帶領一個團隊。兩年時間，從零開始，<strong className="text-[var(--gold-light)]">一路升到最高階 — 皇家團隊長</strong>。不靠背景、不靠資源，靠的是真心分享和不放棄的堅持。
                  </p>
                  <p className="gs-line">
                    這不是一個天才的故事。這是一個<strong className="text-white/90">普通媽媽決定為自己活一次</strong>的故事。如果朵朵可以，你也可以。
                  </p>
                </div>
              </div>

              {/* Milestones — interactive hover */}
              <div className="gs-stagger mt-16 grid grid-cols-3 gap-4">
                {[
                  { num: '2', unit: '年', label: '升到最高階' },
                  { num: '36', unit: '歲', label: '素人起步' },
                  { num: '皇家', unit: '', label: '團隊長' },
                ].map((m) => (
                  <div key={m.label} className="gs-si glow-border text-center p-5 rounded-2xl border border-[var(--gold)]/15 bg-white/[0.03] cursor-default">
                    <div className="text-2xl sm:text-3xl font-black text-[var(--gold)]">
                      {m.num}<span className="text-lg font-medium text-[var(--gold-dim)]">{m.unit}</span>
                    </div>
                    <div className="text-[11px] text-white/40 mt-1 font-medium">{m.label}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          3. 品牌故事 — Fairy → Pandora
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-36" style={{ background: 'linear-gradient(180deg, #12100e 0%, var(--dark) 100%)' }}>
        <FlowingGoldLines density={3} />
        <div className="relative max-w-4xl mx-auto px-6 sm:px-8 z-10">
          <div className="gs-reveal text-center mb-20">
            <span className="text-[11px] font-medium tracking-[0.5em] text-[var(--gold)] uppercase">Our Story</span>
            <h2 className="fp-heading text-3xl sm:text-5xl mt-4">
              <span className="text-shimmer">為什麼叫 Fairy Pandora？</span>
            </h2>
          </div>

          <div className="grid md:grid-cols-2 gap-8">
            <TiltCard className="gs-left glow-border p-8 sm:p-10 rounded-2xl border border-[var(--gold)]/15 bg-[var(--dark-card)]">
              <div className="w-14 h-14 mb-5">
                <svg viewBox="0 0 56 56" fill="none" className="w-full h-full">
                  {/* Wand */}
                  <line x1="12" y1="44" x2="32" y2="14" stroke="var(--gold)" strokeWidth="1.5" strokeLinecap="round" />
                  {/* Wand tip glow */}
                  <circle cx="32" cy="14" r="4" fill="var(--gold)" opacity="0.15" className="fairy-tip-glow" />
                  <circle cx="32" cy="14" r="2" fill="var(--gold-light)" />
                  {/* Wings */}
                  <path d="M28 18 Q20 8 16 18 Q22 15 28 18Z" fill="var(--gold)" opacity="0.12" />
                  <path d="M36 18 Q44 8 48 18 Q42 15 36 18Z" fill="var(--gold)" opacity="0.12" />
                  {/* Animated sparkles */}
                  <path className="fairy-spark-1" d="M38 6 l1.2 3.5 l3.5 1.2 l-3.5 1.2 l-1.2 3.5 l-1.2-3.5 l-3.5-1.2 l3.5-1.2z" fill="var(--gold-light)" />
                  <path className="fairy-spark-2" d="M20 4 l0.8 2.5 l2.5 0.8 l-2.5 0.8 l-0.8 2.5 l-0.8-2.5 l-2.5-0.8 l2.5-0.8z" fill="var(--gold-bright)" />
                  <path className="fairy-spark-3" d="M46 20 l0.6 2 l2 0.6 l-2 0.6 l-0.6 2 l-0.6-2 l-2-0.6 l2-0.6z" fill="var(--gold-light)" />
                  <path className="fairy-spark-4" d="M14 10 l0.5 1.5 l1.5 0.5 l-1.5 0.5 l-0.5 1.5 l-0.5-1.5 l-1.5-0.5 l1.5-0.5z" fill="var(--gold)" />
                  {/* Dust trail */}
                  <circle cx="26" cy="22" r="0.8" fill="var(--gold-light)" opacity="0.4" className="fairy-spark-2" />
                  <circle cx="22" cy="28" r="0.6" fill="var(--gold)" opacity="0.3" className="fairy-spark-3" />
                  <circle cx="18" cy="34" r="0.5" fill="var(--gold-dim)" opacity="0.2" className="fairy-spark-1" />
                </svg>
              </div>
              <h3 className="text-xl font-black text-[var(--gold)]">Fairy · 仙女</h3>
              <p className="text-sm text-white/50 mt-4 leading-[1.9]">
                每個女生心裡都住著一位仙女。只是在忙碌的生活裡，漸漸忘了自己可以更好、可以更自信、可以不用將就。
              </p>
              <p className="text-sm text-white/50 mt-3 leading-[1.9]">
                婕樂纖是那個提醒你的契機 — <strong className="text-white/80">不是變成別人，而是找回自己</strong>。
              </p>
            </TiltCard>
            <TiltCard className="gs-right glow-border p-8 sm:p-10 rounded-2xl border border-[var(--gold)]/15 bg-[var(--dark-card)]">
              <div className="w-16 h-16 mb-5">
                <svg viewBox="0 0 56 56" fill="none" className="w-full h-full">
                  {/* Box body */}
                  <rect x="12" y="28" width="32" height="22" rx="3" fill="var(--dark-card)" stroke="var(--gold)" strokeWidth="1.2" opacity="0.7" />
                  <line x1="28" y1="30" x2="28" y2="50" stroke="var(--gold)" strokeWidth="0.5" opacity="0.2" />
                  {/* Keyhole / lock detail */}
                  <circle cx="28" cy="38" r="2" stroke="var(--gold)" strokeWidth="0.6" opacity="0.3" fill="none" />
                  <line x1="28" y1="40" x2="28" y2="43" stroke="var(--gold)" strokeWidth="0.6" opacity="0.3" />
                  {/* Lid — opens left & right like a chest */}
                  <path className="box-lid-l" d="M12 28 L12 24 Q12 20 16 18 L28 22 L28 28 Z" fill="var(--dark-card)" stroke="var(--gold-light)" strokeWidth="0.8" opacity="0.6" />
                  <path className="box-lid-r" d="M44 28 L44 24 Q44 20 40 18 L28 22 L28 28 Z" fill="var(--dark-card)" stroke="var(--gold-light)" strokeWidth="0.8" opacity="0.6" />
                  {/* Light rays — more, brighter */}
                  <path className="box-ray box-ray-1" d="M28 22 L28 4" stroke="var(--gold-light)" strokeWidth="1.2" strokeLinecap="round" />
                  <path className="box-ray box-ray-2" d="M28 22 L18 6" stroke="var(--gold-light)" strokeWidth="0.8" strokeLinecap="round" />
                  <path className="box-ray box-ray-3" d="M28 22 L38 6" stroke="var(--gold-light)" strokeWidth="0.8" strokeLinecap="round" />
                  <path className="box-ray box-ray-4" d="M28 22 L10 12" stroke="var(--gold)" strokeWidth="0.6" strokeLinecap="round" />
                  <path className="box-ray box-ray-5" d="M28 22 L46 12" stroke="var(--gold)" strokeWidth="0.6" strokeLinecap="round" />
                  <path className="box-ray box-ray-6" d="M28 22 L22 2" stroke="var(--gold)" strokeWidth="0.5" strokeLinecap="round" />
                  <path className="box-ray box-ray-7" d="M28 22 L34 2" stroke="var(--gold)" strokeWidth="0.5" strokeLinecap="round" />
                  {/* Central glow — bigger, brighter */}
                  <circle cx="28" cy="22" r="12" fill="var(--gold-light)" className="box-glow-big" />
                  <circle cx="28" cy="22" r="5" fill="var(--gold-light)" opacity="0.2" className="box-glow" />
                  <circle cx="28" cy="22" r="2" fill="var(--gold-bright)" opacity="0.5" />
                  {/* Light particles flying out */}
                  <circle cx="22" cy="16" r="1" fill="var(--gold-bright)" className="box-particle box-p1" />
                  <circle cx="34" cy="14" r="0.8" fill="var(--gold-light)" className="box-particle box-p2" />
                  <circle cx="28" cy="10" r="1.2" fill="var(--gold-bright)" className="box-particle box-p3" />
                  <circle cx="18" cy="12" r="0.7" fill="var(--gold)" className="box-particle box-p4" />
                  <circle cx="38" cy="10" r="0.9" fill="var(--gold-light)" className="box-particle box-p5" />
                  <circle cx="25" cy="8" r="0.6" fill="var(--gold-bright)" className="box-particle box-p6" />
                </svg>
              </div>
              <h3 className="text-xl font-black text-[var(--gold-light)]">Pandora · 潘朵拉</h3>
              <p className="text-sm text-white/50 mt-4 leading-[1.9]">
                潘朵拉打開了盒子，人們只記得災難。但故事最後，盒子底下藏著的是<strong className="text-white/80">希望</strong>。
              </p>
              <p className="text-sm text-white/50 mt-3 leading-[1.9]">
                打開那個盒子需要勇氣 — 而我們在這裡，陪你一起。裡面裝的是屬於你的健康、美麗、自信，和一個<strong className="text-white/80">全新的自己</strong>。
              </p>
            </TiltCard>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          4. GROWTH JOURNEY — timeline + mascot
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-36" style={{ background: 'var(--dark)' }}>
        <FlowingGoldLines density={3} />
        <div className="relative max-w-4xl mx-auto px-6 sm:px-8 z-10">
          <div className="gs-reveal text-center mb-24">
            <span className="text-[11px] font-medium tracking-[0.5em] text-[var(--gold)] uppercase">Your Journey</span>
            <h2 className="fp-heading text-3xl sm:text-5xl mt-4">
              <span className="text-shimmer">從仙女到潘朵拉</span>
            </h2>
            <p className="text-sm text-white/35 mt-5 max-w-lg mx-auto leading-relaxed">
              每一位來到 FP 的女生，都在經歷一段屬於自己的蛻變。<br />
              這不是劇本 — 是我們親眼見證過無數次的真實故事。
            </p>
          </div>

          <div className="relative">
            <div className="absolute left-7 sm:left-1/2 top-0 bottom-0 w-px sm:-translate-x-px" style={{
              background: 'linear-gradient(to bottom, transparent, var(--gold-dim), var(--gold), var(--gold-dim), transparent)',
            }} />

            {[
              {
                step: '01', stage: 'seedling' as const, mood: 'neutral' as const,
                title: '遇見', subtitle: 'Encounter',
                desc: '也許是朋友的推薦、也許是滑到一篇文章。第一次認識婕樂纖的那個瞬間，你可能還半信半疑。沒關係，朵朵當初也是這樣。',
                detail: '有些改變，就是從一個小小的「試試看」開始的。在 FP，我們不會催你，你可以慢慢看、慢慢了解。',
              },
              {
                step: '02', stage: 'sprout' as const, mood: 'happy' as const,
                title: '探索', subtitle: 'Explore',
                desc: '了解三階梯定價的聰明之處 — 單件試試、兩件組合、滿額 VIP。找到最適合自己的搭配，開始期待每一天的改變。',
                detail: '沒有限時、沒有壓力、價格永遠透明。你值得用最好的價格，買到最好的東西。',
              },
              {
                step: '03', stage: 'sprout' as const, mood: 'excited' as const,
                title: '蛻變', subtitle: 'Transform',
                desc: '加入營養師陪伴班之後，改變不再是一個人的事。有人幫你看飲食、有人在你想放棄時推你一把。',
                detail: '當你某天照鏡子，突然覺得「我好像不一樣了」— 那種感覺，經歷過的人都懂。',
              },
              {
                step: '04', stage: 'bloom' as const, mood: 'excited' as const,
                title: '綻放', subtitle: 'Bloom',
                desc: '花開了。你不只是外表變了 — 你變得更自信、更了解自己。你開始把這份美好分享給身邊的人。',
                detail: '很多 FP 的回頭客，都是被朋友推薦來的。這就是我們最驕傲的事 — 不靠廣告，靠真實的改變。',
              },
            ].map((s, i) => (
              <div key={s.step} className={`relative flex flex-col sm:flex-row items-start gap-8 sm:gap-14 mb-28 last:mb-0 ${i % 2 === 1 ? 'sm:flex-row-reverse' : ''}`}>
                <div className="absolute left-7 sm:left-1/2 top-3 z-10 -translate-x-1/2">
                  <div className="w-4 h-4 rounded-full border-2 border-[var(--gold)] bg-[var(--dark)] shadow-[0_0_12px_rgba(201,168,76,0.3)]" />
                </div>

                <div className={`flex-1 pl-16 sm:pl-0 ${i % 2 === 0 ? 'sm:pr-20 sm:text-right' : 'sm:pl-20'}`}>
                  <div className={i % 2 === 0 ? 'gs-right' : 'gs-left'}>
                    <span className="text-[12px] font-light tracking-[0.3em] text-[var(--gold)]">STEP {s.step}</span>
                    <h3 className="text-3xl sm:text-4xl font-black text-white mt-2 tracking-tight">{s.title}</h3>
                    <span className="text-[10px] font-medium tracking-[0.3em] text-[var(--gold-dim)] uppercase">{s.subtitle}</span>
                    <p className="text-sm text-white/50 mt-5 leading-[1.9]">{s.desc}</p>
                    <p className="text-sm text-white/30 mt-3 leading-[1.9] italic">{s.detail}</p>
                  </div>
                </div>

                <div className={`hidden sm:flex flex-1 ${i % 2 === 0 ? 'justify-start pl-20' : 'justify-end pr-20'}`}>
                  <div className="gs-scale">
                    <div className="glow-border w-44 h-44 rounded-3xl border border-[var(--gold)]/15 bg-[var(--dark-card)] flex items-center justify-center cursor-default">
                      <Mascot stage={s.stage} mood={s.mood} size={120} />
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          5. FP 的陪伴系統
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-36" style={{ background: 'linear-gradient(180deg, var(--dark) 0%, #12100e 100%)' }}>
        <FlowingGoldLines density={3} />
        <div className="relative max-w-5xl mx-auto px-6 sm:px-8 z-10">
          <div className="gs-reveal text-center mb-20">
            <span className="text-[11px] font-medium tracking-[0.5em] text-[var(--gold)] uppercase">Support System</span>
            <h2 className="fp-heading text-3xl sm:text-5xl mt-4">
              <span className="text-shimmer">你不是一個人</span>
            </h2>
            <p className="text-sm text-white/45 mt-5 max-w-lg mx-auto leading-relaxed">
              加入 FP，不只是買到好產品。你會得到一整個團隊的支持 —<br />
              從產品諮詢到創業陪伴，每一步都有人陪你走。
            </p>
          </div>

          {/* 3-column support cards */}
          <div className="gs-stagger grid sm:grid-cols-3 gap-6 mb-20">
            {[
              {
                title: '客服仙女',
                sub: '1 對 1 LINE 諮詢',
                desc: '每位顧客都有專屬客服，從選品建議到售後追蹤。不是機器人，是真的在乎你的人。平均 1 小時內回覆。',
                icon: (
                  <svg viewBox="0 0 40 40" className="w-10 h-10" fill="none">
                    <rect x="8" y="4" width="24" height="32" rx="6" stroke="var(--gold)" strokeWidth="1" opacity="0.5" />
                    <rect x="12" y="12" width="14" height="5" rx="2.5" fill="var(--gold)" opacity="0.2" />
                    <rect x="14" y="20" width="14" height="5" rx="2.5" fill="var(--gold)" opacity="0.15" />
                    <rect x="12" y="28" width="10" height="5" rx="2.5" fill="var(--gold)" opacity="0.1" />
                    <circle cx="30" cy="8" r="3" fill="var(--gold)" opacity="0.4" />
                  </svg>
                ),
              },
              {
                title: '團隊培訓',
                sub: '系統化教學資源',
                desc: '想經營 FP 事業？從產品知識、社群經營到顧客溝通，完整培訓系統讓你不用從零摸索，有方向地成長。',
                icon: (
                  <svg viewBox="0 0 40 40" className="w-10 h-10" fill="none">
                    <rect x="6" y="8" width="18" height="24" rx="2" stroke="var(--gold)" strokeWidth="0.8" opacity="0.4" />
                    <rect x="16" y="4" width="18" height="24" rx="2" stroke="var(--gold)" strokeWidth="0.8" opacity="0.5" fill="var(--dark-card)" />
                    <line x1="20" y1="11" x2="30" y2="11" stroke="var(--gold)" strokeWidth="0.6" opacity="0.3" />
                    <line x1="20" y1="15" x2="28" y2="15" stroke="var(--gold)" strokeWidth="0.6" opacity="0.25" />
                    <line x1="20" y1="19" x2="29" y2="19" stroke="var(--gold)" strokeWidth="0.6" opacity="0.2" />
                    <path d="M14 32 l4 4 l8-10" stroke="var(--gold-light)" strokeWidth="1.2" strokeLinecap="round" strokeLinejoin="round" opacity="0.5" />
                  </svg>
                ),
              },
              {
                title: '社群力量',
                sub: '夥伴互助共好',
                desc: '加入 FP 社群，和一群志同道合的夥伴交流經驗。不是單打獨鬥，而是一起成長、一起慶祝每一個小小的突破。',
                icon: (
                  <svg viewBox="0 0 40 40" className="w-10 h-10" fill="none">
                    <circle cx="20" cy="14" r="5" stroke="var(--gold)" strokeWidth="0.8" opacity="0.5" />
                    <circle cx="10" cy="18" r="4" stroke="var(--gold)" strokeWidth="0.6" opacity="0.3" />
                    <circle cx="30" cy="18" r="4" stroke="var(--gold)" strokeWidth="0.6" opacity="0.3" />
                    <path d="M8 30 Q8 24 14 24 L26 24 Q32 24 32 30" stroke="var(--gold)" strokeWidth="0.8" opacity="0.4" fill="none" />
                    <path d="M2 33 Q2 28 7 28 L12 28" stroke="var(--gold)" strokeWidth="0.6" opacity="0.25" fill="none" />
                    <path d="M38 33 Q38 28 33 28 L28 28" stroke="var(--gold)" strokeWidth="0.6" opacity="0.25" fill="none" />
                    <path d="M16 36 l2-2 l2 2" stroke="var(--gold-light)" strokeWidth="0.8" strokeLinecap="round" opacity="0.4" />
                    <path d="M22 35 l1.5-1.5 l1.5 1.5" stroke="var(--gold-light)" strokeWidth="0.6" strokeLinecap="round" opacity="0.3" />
                  </svg>
                ),
              },
            ].map((card) => (
              <TiltCard key={card.title} className="gs-si glow-border p-7 sm:p-8 rounded-2xl border border-[var(--gold)]/15 bg-[var(--dark-card)]">
                <div className="mb-4">{card.icon}</div>
                <h3 className="text-lg font-black text-white/90">{card.title}</h3>
                <span className="text-[10px] font-medium tracking-[0.2em] text-[var(--gold-dim)] uppercase mt-1 block">{card.sub}</span>
                <p className="text-sm text-white/40 mt-4 leading-[1.85]">{card.desc}</p>
              </TiltCard>
            ))}
          </div>

          {/* Bottom highlight */}
          <div className="gs-reveal text-center">
            <p className="text-xl sm:text-2xl font-bold text-white/60 leading-relaxed">
              在 FP，你買到的不只是產品 —<br />
              是一個<strong className="text-[var(--gold-light)]">真心希望你變好</strong>的團隊。
            </p>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          6. VISION + PROMISE — 願景與承諾
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-36 relative" style={{ background: '#12100e' }}>
        <FlowingGoldLines density={5} />
        <RippleBackground opacity={0.04} />
        <div className="relative max-w-5xl mx-auto px-6 sm:px-8 z-10">

          {/* Vision header */}
          <div className="gs-reveal text-center mb-16">
            <span className="fp-en text-[11px] font-medium tracking-[0.5em] text-[var(--gold)] uppercase">Our Vision & Promise</span>
            <h2 className="fp-heading text-3xl sm:text-5xl mt-4">
              <span className="text-shimmer">我們的信念</span>
            </h2>
          </div>

          {/* Vision statement + 3 promises side by side */}
          <div className="flex flex-col lg:flex-row gap-12 lg:gap-16 items-start mb-20">
            {/* Left: big statement */}
            <div className="flex-1 gs-left">
              <p className="text-sm sm:text-base text-white/35 leading-relaxed mb-8">
                台灣的健康食品市場充斥著誇大的廣告、模糊的成分標示、和「買了就不管你」的銷售模式。我們相信，應該有一個地方是不一樣的。
              </p>
              <div className="relative">
                <div className="absolute -inset-4 rounded-3xl opacity-[0.05]" style={{
                  background: 'radial-gradient(ellipse, var(--gold), transparent 70%)',
                }} />
                <div className="relative space-y-6">
                  {[
                    { word: '安心', desc: '每一件都是 JEROSSE 原廠出貨，正品保證。' },
                    { word: '放心', desc: '不催促、不話術。你的節奏就是最好的節奏。' },
                    { word: '看得見', desc: '三階梯定價透明公開，改變用時間證明。' },
                  ].map((item, i) => (
                    <div key={item.word} className="gs-reveal flex items-start gap-5">
                      <span className="text-shimmer text-3xl sm:text-4xl font-black shrink-0 w-24 sm:w-28 text-right">{item.word}</span>
                      <div className="pt-2">
                        <div className="w-8 h-px bg-[var(--gold)]/30 mb-3" />
                        <p className="text-sm text-white/40 leading-relaxed">{item.desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              <div className="gs-reveal mt-10">
                <p className="text-base sm:text-lg text-white/50 leading-relaxed">
                  FP 要做的不是最大的電商，<br />
                  而是<strong className="text-white/80 font-bold">最值得信任的那一個</strong>。
                </p>
              </div>
            </div>

            {/* Right / Bottom: stats */}
            <div className="gs-right w-full lg:w-[280px] shrink-0">
              <div className="glow-border rounded-3xl border border-[var(--gold)]/15 bg-[var(--dark-card)] p-6 sm:p-8">
                {/* Mobile: 3-col grid / Desktop: vertical stack */}
                <div className="grid grid-cols-3 lg:grid-cols-1 gap-4 lg:gap-6 text-center">
                  {[
                    { val: '8+', unit: '年', label: '深耕健康產業' },
                    { val: '100', unit: '%', label: '官方正品' },
                    { val: '1', unit: 'hr', label: '客服回覆' },
                  ].map((s) => (
                    <div key={s.label}>
                      <div className="fp-en text-2xl lg:text-3xl font-black text-[var(--gold)]">
                        {s.val}<span className="text-base lg:text-lg text-[var(--gold-dim)]">{s.unit}</span>
                      </div>
                      <div className="text-[9px] lg:text-[10px] text-white/30 mt-1 font-medium tracking-wider">{s.label}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          8. CTA
      ═══════════════════════════════════════════ */}
      <section className="py-28 sm:py-36 relative" style={{ background: 'linear-gradient(180deg, var(--dark-card) 0%, var(--dark) 100%)' }}>
        <FlowingGoldLines density={6} />
        <RippleBackground opacity={0.05} />
        <div className="relative max-w-2xl mx-auto px-6 text-center z-10">
          <div className="gs-scale mb-10">
            <Mascot stage="bloom" mood="excited" size={150} />
          </div>
          <div className="gs-reveal">
            <h2 className="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight tracking-tight text-white/90">
              準備好打開你的<br />
              <span className="fp-heading text-shimmer">潘朵拉盒子了嗎？</span>
            </h2>
            <p className="text-sm text-white/40 mt-6 max-w-md mx-auto leading-relaxed">
              每個蛻變都從一小步開始。<br />
              無論你是第一次認識婕樂纖，或是想加入 FP 團隊 — 我們都在這裡等你。
            </p>
          </div>
          <div className="gs-reveal mt-12 flex flex-col sm:flex-row gap-3 justify-center">
            <MagneticButton>
              <Link
                href="/products"
                className="px-10 py-4 font-black rounded-full shadow-lg shadow-[var(--gold)]/25 min-h-[56px] flex items-center justify-center text-base"
                style={{ background: 'linear-gradient(135deg, var(--gold), var(--gold-dim))', color: '#0d0b09' }}
              >
                開始選購
              </Link>
            </MagneticButton>
            <MagneticButton>
              <Link
                href="/articles"
                className="px-10 py-4 font-black rounded-full border border-[var(--gold)]/30 text-[var(--gold)] hover:bg-[var(--gold)]/10 transition-colors min-h-[56px] flex items-center justify-center text-base"
              >
                閱讀仙女誌 →
              </Link>
            </MagneticButton>
          </div>
        </div>
      </section>
    </div>
  );
}

/* ═══════════════════════════════════════════════════════
   Interactive Components
═══════════════════════════════════════════════════════ */

/** Flowing gold lines — canvas-based ambient animation */
function FlowingGoldLines({ density = 5 }: { density?: number }) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let animId: number;
    let lines: { x: number; y: number; vx: number; vy: number; life: number; maxLife: number; curve: number; trail: { x: number; y: number }[] }[] = [];

    const resize = () => {
      const rect = canvas.parentElement?.getBoundingClientRect();
      if (!rect) return;
      canvas.width = rect.width;
      canvas.height = rect.height;
    };
    resize();
    window.addEventListener('resize', resize);

    const spawnLine = () => {
      const edge = Math.random();
      let x: number, y: number, vx: number, vy: number;
      if (edge < 0.25) { x = 0; y = Math.random() * canvas.height; vx = 0.4 + Math.random() * 0.6; vy = (Math.random() - 0.5) * 0.4; }
      else if (edge < 0.5) { x = canvas.width; y = Math.random() * canvas.height; vx = -(0.4 + Math.random() * 0.6); vy = (Math.random() - 0.5) * 0.4; }
      else if (edge < 0.75) { x = Math.random() * canvas.width; y = 0; vx = (Math.random() - 0.5) * 0.4; vy = 0.4 + Math.random() * 0.6; }
      else { x = Math.random() * canvas.width; y = canvas.height; vx = (Math.random() - 0.5) * 0.4; vy = -(0.4 + Math.random() * 0.6); }
      const trail: { x: number; y: number }[] = [];
      lines.push({ x, y, vx, vy, life: 0, maxLife: 250 + Math.random() * 350, curve: (Math.random() - 0.5) * 0.004, trail });
    };

    // Initial lines
    for (let i = 0; i < density * 2; i++) spawnLine();

    const draw = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      // Spawn new lines more frequently
      if (lines.length < density * 4 && Math.random() < 0.05) spawnLine();

      lines = lines.filter((l) => l.life < l.maxLife);

      for (const l of lines) {
        const progress = l.life / l.maxLife;
        const alpha = progress < 0.1 ? progress * 10 : progress > 0.75 ? (1 - progress) * 4 : 1;

        // Store trail points
        l.trail.push({ x: l.x, y: l.y });
        if (l.trail.length > 40) l.trail.shift();

        // Curve the velocity
        l.vx += l.curve;
        l.vy += l.curve * 0.5;
        l.x += l.vx;
        l.y += l.vy;
        l.life++;

        // Draw trail with fading segments
        if (l.trail.length > 2) {
          for (let t = 1; t < l.trail.length; t++) {
            const trailAlpha = (t / l.trail.length) * alpha;
            ctx.beginPath();
            ctx.moveTo(l.trail[t - 1].x, l.trail[t - 1].y);
            ctx.lineTo(l.trail[t].x, l.trail[t].y);
            ctx.strokeStyle = `rgba(201, 168, 76, ${trailAlpha * 0.3})`;
            ctx.lineWidth = (t / l.trail.length) * 1.5;
            ctx.stroke();
          }
        }

        // Bright glowing head
        ctx.beginPath();
        ctx.arc(l.x, l.y, 2, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(232, 212, 139, ${alpha * 0.7})`;
        ctx.fill();

        // Soft glow around head
        ctx.beginPath();
        ctx.arc(l.x, l.y, 6, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(201, 168, 76, ${alpha * 0.1})`;
        ctx.fill();
      }

      animId = requestAnimationFrame(draw);
    };
    draw();

    return () => {
      cancelAnimationFrame(animId);
      window.removeEventListener('resize', resize);
    };
  }, [density]);

  return <canvas ref={canvasRef} className="flow-canvas" />;
}

/** Ripple rings from center */
function RippleBackground({ opacity = 0.04 }: { opacity?: number }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const rings = Array.from({ length: 7 }, (_, i) => {
      const ring = document.createElement('div');
      ring.className = 'ripple-ring';
      const size = 120 + i * 130;
      Object.assign(ring.style, {
        width: `${size}px`, height: `${size}px`,
        left: '50%', top: '50%',
        marginLeft: `-${size / 2}px`, marginTop: `-${size / 2}px`,
        animationDelay: `${i * 0.85}s`, opacity: '0',
      });
      el.appendChild(ring);
      return ring;
    });
    return () => rings.forEach((r) => r.remove());
  }, []);
  return <div ref={ref} className="absolute inset-0 pointer-events-none" style={{ opacity }} />;
}

/** 3D tilt on hover */
function TiltCard({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const handleMove = useCallback((e: React.MouseEvent) => {
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width - 0.5;
    const y = (e.clientY - rect.top) / rect.height - 0.5;
    el.style.transform = `perspective(800px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg) scale(1.02)`;
  }, []);
  const handleLeave = useCallback(() => {
    const el = ref.current;
    if (el) el.style.transform = 'perspective(800px) rotateY(0deg) rotateX(0deg) scale(1)';
  }, []);
  return (
    <div ref={ref} className={`tilt-card ${className}`} onMouseMove={handleMove} onMouseLeave={handleLeave}>
      {children}
    </div>
  );
}

/** Mouse parallax layer */
function MouseParallax({ children, className = '', factor = 0.03 }: { children: React.ReactNode; className?: string; factor?: number }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const handle = (e: MouseEvent) => {
      const x = (e.clientX / window.innerWidth - 0.5) * factor * 100;
      const y = (e.clientY / window.innerHeight - 0.5) * factor * 100;
      el.style.transform = `translate(${x}px, ${y}px)`;
    };
    window.addEventListener('mousemove', handle, { passive: true });
    return () => window.removeEventListener('mousemove', handle);
  }, [factor]);
  return <div ref={ref} className={`mouse-parallax ${className}`}>{children}</div>;
}

/** Magnetic button — follows cursor slightly */
function MagneticButton({ children }: { children: React.ReactNode }) {
  const ref = useRef<HTMLDivElement>(null);
  const handleMove = useCallback((e: React.MouseEvent) => {
    const el = ref.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const x = e.clientX - rect.left - rect.width / 2;
    const y = e.clientY - rect.top - rect.height / 2;
    el.style.transform = `translate(${x * 0.15}px, ${y * 0.15}px)`;
  }, []);
  const handleLeave = useCallback(() => {
    const el = ref.current;
    if (el) el.style.transform = 'translate(0, 0)';
  }, []);
  return (
    <div ref={ref} className="mag-btn inline-block" onMouseMove={handleMove} onMouseLeave={handleLeave}>
      {children}
    </div>
  );
}
