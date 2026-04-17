'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Mascot from './Mascot';
import Icons from './SvgIcons';
import { useAuth } from './AuthProvider';
import { useToast } from './Toast';
import { getCustomerDashboard, type CustomerDashboard } from '@/lib/api';
import { stageFromStreak, type MascotStage, type MascotMood } from '@/lib/achievements';

/**
 * Hero orbit visual — central glowing orb with 芽芽 mascot at center,
 * floating badges + orbit rings.
 *
 * When logged in: reads user's outfit, backdrop, streak stage.
 * Backdrop changes the ORB color itself — the whole sphere is 芽芽's world.
 */

// Orb gradient per backdrop — maps backdrop code → radial-gradient for the sphere
const ORB_GRADIENTS: Record<string, string> = {
  default: 'radial-gradient(circle at 30% 30%, #ffffff, #f7c79a 45%, #c9935a 80%, #9F6B3E 100%)',
  meadow:  'radial-gradient(circle at 30% 30%, #ffffff, #a5d6a7 45%, #66bb6a 80%, #388e3c 100%)',
  garden:  'radial-gradient(circle at 30% 30%, #ffffff, #f8bbd0 45%, #ec407a 80%, #c2185b 100%)',
  sakura:  'radial-gradient(circle at 30% 30%, #fff0f5, #f48fb1 45%, #e91e63 80%, #ad1457 100%)',
  starry:  'radial-gradient(circle at 30% 30%, #e8eaf6, #7986cb 45%, #3949ab 80%, #1a237e 100%)',
  rainbow: 'radial-gradient(circle at 30% 30%, #ffffff, #fad0c4 35%, #a1c4fd 65%, #667eea 100%)',
  beach:   'radial-gradient(circle at 30% 30%, #ffffff, #80deea 45%, #26c6da 80%, #0097a7 100%)',
};

const ORB_SHADOWS: Record<string, string> = {
  default: '0 30px 80px -20px rgba(159,107,62,0.5), inset 0 0 80px rgba(255,255,255,0.4)',
  meadow:  '0 30px 80px -20px rgba(56,142,60,0.4), inset 0 0 80px rgba(255,255,255,0.4)',
  garden:  '0 30px 80px -20px rgba(194,24,91,0.4), inset 0 0 80px rgba(255,255,255,0.4)',
  sakura:  '0 30px 80px -20px rgba(173,20,87,0.4), inset 0 0 80px rgba(255,255,255,0.3)',
  starry:  '0 30px 80px -20px rgba(26,35,126,0.5), inset 0 0 80px rgba(200,200,255,0.3)',
  rainbow: '0 30px 80px -20px rgba(102,126,234,0.4), inset 0 0 80px rgba(255,255,255,0.4)',
  beach:   '0 30px 80px -20px rgba(0,151,167,0.4), inset 0 0 80px rgba(255,255,255,0.4)',
};

interface Props {
  size?: number;
  className?: string;
}

export default function HeroOrbit({ size = 420, className = '' }: Props) {
  const { token, isLoggedIn } = useAuth();
  const [mascotState, setMascotState] = useState<{
    stage: MascotStage; mood: MascotMood;
    outfit: string | null; backdrop: string | null;
  }>({ stage: 'sprout', mood: 'happy', outfit: null, backdrop: null });

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    let cancelled = false;
    getCustomerDashboard(token).then((d) => {
      if (cancelled) return;
      setMascotState({
        stage: stageFromStreak(d.customer.streak_days),
        mood: d.customer.streak_days >= 3 ? 'excited' : 'happy',
        outfit: d.customer.current_outfit,
        backdrop: d.customer.current_backdrop,
      });
    }).catch(() => {});
    return () => { cancelled = true; };
  }, [token, isLoggedIn]);

  const router = useRouter();
  const { toast } = useToast();
  const [tapped, setTapped] = useState<string | null>(null);

  const backdropKey = mascotState.backdrop || 'default';
  const orbGradient = ORB_GRADIENTS[backdropKey] || ORB_GRADIENTS.default;
  const orbShadow = ORB_SHADOWS[backdropKey] || ORB_SHADOWS.default;

  const tap = useCallback((id: string, msg: string, href?: string) => {
    setTapped(id);
    toast(msg);
    setTimeout(() => setTapped(null), 400);
    if (href) setTimeout(() => router.push(href), 300);
  }, [toast, router]);
  const s = size / 420;

  const bigBadge = Math.round(100 * s);
  const midBadge = Math.round(80 * s);
  const smBadge = Math.round(56 * s);
  const bigEmoji = Math.round(32 * s);
  const midEmoji = Math.round(26 * s);
  const smEmoji = Math.round(20 * s);
  const label = Math.round(10 * s);
  const tinyDot = Math.round(8 * s);

  return (
    <div
      className={`relative ${className}`}
      style={{ width: size, height: size }}
    >
      {/* Orbit ring paths */}
      <svg className="absolute inset-0 w-full h-full hero-ring-spin" viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="44" fill="none" stroke="#9F6B3E" strokeWidth="0.15" opacity="0.25" strokeDasharray="3 5" />
        <circle cx="50" cy="50" r="52" fill="none" stroke="#9F6B3E" strokeWidth="0.1" opacity="0.15" strokeDasharray="2 6" />
      </svg>

      {/* Central orb — backdrop changes the entire sphere color */}
      <div
        className="absolute rounded-full hero-orb transition-all duration-1000"
        style={{
          inset: '8%',
          background: orbGradient,
          boxShadow: orbShadow,
        }}
      />

      {/* Inner glow pulse */}
      <div
        className="absolute rounded-full hero-pulse"
        style={{
          inset: '15%',
          background: 'radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%)',
        }}
      />

      {/* 芽芽 mascot at the center — tap to visit */}
      <button
        type="button"
        onClick={() => tap('mascot', isLoggedIn ? '進入芽芽之家 🌱' : '登入解鎖芽芽 🌱', isLoggedIn ? '/account/mascot' : '/account')}
        className={`absolute flex items-center justify-center hero-mascot-float cursor-pointer transition-transform duration-300 ${tapped === 'mascot' ? 'scale-125' : ''}`}
        style={{ inset: '22%' }}
        aria-label="進入芽芽之家"
      >
        <Mascot
          stage={mascotState.stage}
          mood={tapped === 'mascot' ? 'excited' : mascotState.mood}
          outfit={mascotState.outfit}
          size={Math.round(size * 0.38)}
        />
      </button>

      {/* 健康內在 (right-top) */}
      <button
        type="button"
        onClick={() => tap('health', '保健食品 · 由內而外 🌿', '/products?category=health')}
        className={`absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-a rounded-2xl cursor-pointer transition-transform duration-300 ${tapped === 'health' ? 'scale-110' : 'active:scale-95'}`}
        style={{
          width: bigBadge, height: bigBadge,
          top: '4%', right: '-8%',
        }}
      >
        <Icons.Leaf className={`text-[#4A9D5F] icon-sway`} style={{ width: bigEmoji, height: bigEmoji }} />
        <div className="font-black text-[#4A9D5F] mt-0.5" style={{ fontSize: label }}>健康內在</div>
      </button>

      {/* 美容外在 (right-bottom) */}
      <button
        type="button"
        onClick={() => tap('beauty', '美容保養 · 綻放光彩', '/products?category=beauty')}
        className={`absolute bg-white shadow-2xl flex flex-col items-center justify-center hero-orbit-b rounded-2xl cursor-pointer transition-transform duration-300 ${tapped === 'beauty' ? 'scale-110' : 'active:scale-95'}`}
        style={{
          width: bigBadge, height: bigBadge,
          bottom: '4%', right: '-4%',
        }}
      >
        <Icons.Sparkles className={`text-[#E0748C] icon-pulse`} style={{ width: bigEmoji, height: bigEmoji }} />
        <div className="font-black text-[#E0748C] mt-0.5" style={{ fontSize: label }}>美容外在</div>
      </button>

      {/* 玉山獎 (left-bottom) */}
      <button
        type="button"
        onClick={() => tap('award', '2025 玉山獎 · 25 座大獎', '/about')}
        className={`absolute bg-gradient-to-br from-[#9F6B3E] to-[#85572F] shadow-2xl flex flex-col items-center justify-center hero-orbit-c text-white rounded-2xl cursor-pointer transition-transform duration-300 ${tapped === 'award' ? 'scale-110' : 'active:scale-95'}`}
        style={{
          width: midBadge, height: midBadge,
          bottom: '12%', left: '-8%',
        }}
      >
        <Icons.Trophy className="text-white icon-shimmer" style={{ width: midEmoji, height: midEmoji }} />
        <div className="font-black mt-0.5" style={{ fontSize: label }}>玉山獎</div>
      </button>

      {/* 健康食品認證 (top-left) */}
      <button
        type="button"
        onClick={() => tap('cert', '衛福部健康食品認證', '/products')}
        className={`absolute bg-gradient-to-br from-[#e8f5e9] to-[#c8e6c9] shadow-lg flex flex-col items-center justify-center hero-orbit-e rounded-2xl border border-[#a5d6a7] cursor-pointer transition-transform duration-300 ${tapped === 'cert' ? 'scale-110' : 'active:scale-95'}`}
        style={{
          width: midBadge, height: midBadge,
          top: '18%', left: '-12%',
        }}
      >
        <Icons.Shield className="text-[#2e7d32] icon-float" style={{ width: midEmoji, height: midEmoji }} />
        <div className="font-black text-[#2e7d32] mt-0.5" style={{ fontSize: label }}>小綠人認證</div>
      </button>

      {/* VIP bubble (left-middle) */}
      <button
        type="button"
        onClick={() => tap('vip', '滿 $4,000 解鎖 VIP 價', '/products')}
        className={`absolute bg-white/90 backdrop-blur shadow-lg flex items-center justify-center hero-orbit-d rounded-full cursor-pointer transition-transform duration-300 ${tapped === 'vip' ? 'scale-125' : 'active:scale-90'}`}
        style={{
          width: smBadge, height: smBadge,
          top: '40%', left: '-14%',
        }}
      >
        <Icons.Diamond className="text-[#9F6B3E] icon-pulse" style={{ width: smEmoji, height: smEmoji }} />
      </button>

      {/* 3 階梯 (bottom-center) */}
      <button
        type="button"
        onClick={() => tap('tier', '3 階梯定價 · 買越多越便宜', '/products')}
        className={`absolute bg-white/95 backdrop-blur-sm shadow-lg flex flex-col items-center justify-center hero-orbit-f rounded-xl cursor-pointer transition-transform duration-300 ${tapped === 'tier' ? 'scale-110' : 'active:scale-95'}`}
        style={{
          width: midBadge, height: Math.round(midBadge * 0.7),
          bottom: '-4%', left: '30%',
        }}
      >
        <Icons.TierSteps className="text-[#9F6B3E] icon-shimmer" style={{ width: smEmoji, height: smEmoji }} />
        <div className="font-black text-[#9F6B3E] mt-0.5" style={{ fontSize: label }}>3 階梯定價</div>
      </button>

      {/* Floating micro particles */}
      {[
        { top: '8%', left: '20%', delay: '0s' },
        { top: '65%', right: '8%', delay: '1.5s' },
        { bottom: '25%', left: '5%', delay: '3s' },
        { top: '35%', right: '3%', delay: '2s' },
        { bottom: '8%', left: '55%', delay: '0.8s' },
      ].map((pos, i) => (
        <div
          key={i}
          className="absolute rounded-full hero-particle"
          style={{
            width: tinyDot, height: tinyDot,
            background: 'radial-gradient(circle, rgba(159,107,62,0.4), transparent)',
            animationDelay: pos.delay,
            ...pos,
          }}
        />
      ))}

      <style jsx>{`
        @keyframes hero-orb-float {
          0%, 100% { transform: translate(0, 0) scale(1); }
          50% { transform: translate(8px, -10px) scale(1.02); }
        }
        :global(.hero-orb) { animation: hero-orb-float 6s ease-in-out infinite; will-change: transform; }

        @keyframes hero-pulse {
          0%, 100% { opacity: 0.3; transform: scale(0.95); }
          50% { opacity: 0.6; transform: scale(1.05); }
        }
        :global(.hero-pulse) { animation: hero-pulse 4s ease-in-out infinite; }

        @keyframes hero-mascot-float {
          0%, 100% { transform: translateY(0) scale(1); }
          50% { transform: translateY(-6px) scale(1.03); }
        }
        :global(.hero-mascot-float) { animation: hero-mascot-float 5s ease-in-out infinite 0.5s; }

        @keyframes hero-ring-spin {
          from { transform: rotate(0deg); }
          to { transform: rotate(360deg); }
        }
        :global(.hero-ring-spin) { animation: hero-ring-spin 60s linear infinite; }

        @keyframes hero-orbit-a {
          0%, 100% { transform: translate(0, 0) rotate(-3deg); }
          50% { transform: translate(-6px, 12px) rotate(2deg); }
        }
        @keyframes hero-orbit-b {
          0%, 100% { transform: translate(0, 0) rotate(2deg); }
          50% { transform: translate(8px, -12px) rotate(-2deg); }
        }
        @keyframes hero-orbit-c {
          0%, 100% { transform: translate(0, 0) rotate(-5deg); }
          50% { transform: translate(6px, -10px) rotate(3deg); }
        }
        @keyframes hero-orbit-d {
          0%, 100% { transform: translate(0, 0); }
          50% { transform: translate(-8px, 14px); }
        }
        @keyframes hero-orbit-e {
          0%, 100% { transform: translate(0, 0) rotate(2deg); }
          50% { transform: translate(10px, 8px) rotate(-3deg); }
        }
        @keyframes hero-orbit-f {
          0%, 100% { transform: translate(0, 0) rotate(-1deg); }
          50% { transform: translate(-6px, -8px) rotate(1deg); }
        }
        @keyframes hero-particle {
          0%, 100% { opacity: 0.2; transform: scale(0.5) translateY(0); }
          50% { opacity: 0.8; transform: scale(1.2) translateY(-10px); }
        }
        :global(.hero-orbit-a) { animation: hero-orbit-a 8s ease-in-out infinite; will-change: transform; }
        :global(.hero-orbit-b) { animation: hero-orbit-b 9s ease-in-out infinite 1s; will-change: transform; }
        :global(.hero-orbit-c) { animation: hero-orbit-c 10s ease-in-out infinite 2s; will-change: transform; }
        :global(.hero-orbit-d) { animation: hero-orbit-d 7s ease-in-out infinite 0.5s; will-change: transform; }
        :global(.hero-orbit-e) { animation: hero-orbit-e 11s ease-in-out infinite 1.5s; will-change: transform; }
        :global(.hero-orbit-f) { animation: hero-orbit-f 9s ease-in-out infinite 3s; will-change: transform; }
        :global(.hero-particle) { animation: hero-particle 5s ease-in-out infinite; }

        @media (prefers-reduced-motion: reduce) {
          :global(.hero-orb), :global(.hero-pulse), :global(.hero-ring-spin),
          :global(.hero-orbit-a), :global(.hero-orbit-b), :global(.hero-orbit-c),
          :global(.hero-orbit-d), :global(.hero-orbit-e), :global(.hero-orbit-f),
          :global(.hero-particle), :global(.hero-mascot-float) { animation: none; }
        }
      `}</style>
    </div>
  );
}
