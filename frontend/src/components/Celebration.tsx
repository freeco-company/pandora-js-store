'use client';

/**
 * Celebration — confetti + badge modal on achievement unlock.
 * API responses with `_achievement` / `_achievements` / `_outfits` keys trigger this.
 *
 *   const { celebrate, celebrateMany } = useCelebrate();
 *   const res = await fetch(...);
 *   const data = await res.json();
 *   celebrateMany(data._achievements, data._outfits);
 */

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { ACHIEVEMENT_CATALOG, TIER_GRADIENTS, type AchievementDef } from '@/lib/achievements';
import SiteIcon from '@/components/SiteIcon';
import OutfitIcon from '@/components/OutfitIcon';

interface CelebrationCtx {
  celebrate: (code: string) => void;
  celebrateMany: (...codeGroups: (string[] | undefined | null)[]) => void;
}

const Ctx = createContext<CelebrationCtx>({
  celebrate: () => {},
  celebrateMany: () => {},
});

export const useCelebrate = () => useContext(Ctx);

export function CelebrationProvider({ children }: { children: ReactNode }) {
  const [queue, setQueue] = useState<AchievementDef[]>([]);
  const [active, setActive] = useState<AchievementDef | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const celebrate = useCallback((code: string) => {
    const def = ACHIEVEMENT_CATALOG[code];
    if (!def) return;
    setQueue((q) => [...q, def]);
  }, []);

  const celebrateMany = useCallback((...codeGroups: (string[] | undefined | null)[]) => {
    const defs: AchievementDef[] = [];
    for (const group of codeGroups) {
      if (!group) continue;
      for (const code of group) {
        const def = ACHIEVEMENT_CATALOG[code];
        if (def) defs.push(def);
      }
    }
    if (defs.length) setQueue((q) => [...q, ...defs]);
  }, []);

  useEffect(() => {
    if (active || queue.length === 0) return;
    const [next, ...rest] = queue;
    setActive(next);
    setQueue(rest);
    timerRef.current = setTimeout(() => setActive(null), 2800);
    return () => {
      if (timerRef.current) clearTimeout(timerRef.current);
    };
  }, [queue, active]);

  return (
    <Ctx.Provider value={{ celebrate, celebrateMany }}>
      {children}
      {active && <Overlay achievement={active} onClose={() => setActive(null)} />}
    </Ctx.Provider>
  );
}

function Overlay({ achievement, onClose }: { achievement: AchievementDef; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[300] flex items-center justify-center pointer-events-auto" onClick={onClose}>
      <div className="absolute inset-0 bg-black/30 celebration-backdrop" />
      <ConfettiBurst />
      <div className="relative celebration-card">
        <div className={`bg-gradient-to-br ${TIER_GRADIENTS[achievement.tier]} rounded-3xl p-8 shadow-2xl min-w-[280px] text-center`}>
          <div className="text-7xl mb-3 celebration-emoji"><OutfitIcon name={achievement.emoji} size={72} /></div>
          <div className="text-[10px] font-black text-white/70 tracking-[0.2em] mb-1">成就達成</div>
          <h3 className="text-2xl font-black text-[#3d2e22] mb-1">{achievement.name}</h3>
          <p className="text-xs font-bold text-[#5a4234]/70">{achievement.description}</p>
        </div>
      </div>

      <style jsx>{`
        @keyframes backdropFade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes cardPop {
          0% { opacity: 0; transform: scale(0.5) rotate(-8deg); }
          60% { opacity: 1; transform: scale(1.08) rotate(2deg); }
          100% { opacity: 1; transform: scale(1) rotate(0deg); }
        }
        @keyframes emojiSpin {
          from { transform: rotate(-30deg) scale(0.5); }
          to { transform: rotate(0deg) scale(1); }
        }
        :global(.celebration-backdrop) { animation: backdropFade 0.2s ease-out forwards; }
        :global(.celebration-card) { animation: cardPop 0.5s cubic-bezier(0.2, 0.9, 0.3, 1.2) forwards; }
        :global(.celebration-emoji) {
          display: inline-block;
          animation: emojiSpin 0.6s cubic-bezier(0.2, 0.9, 0.3, 1.2) forwards;
        }
        @media (prefers-reduced-motion: reduce) {
          :global(.celebration-backdrop), :global(.celebration-card), :global(.celebration-emoji) { animation: none; }
        }
      `}</style>
    </div>
  );
}

function ConfettiBurst() {
  const pieces = useRef(
    Array.from({ length: 40 }, (_, i) => ({
      id: i,
      hue: Math.floor(Math.random() * 360),
      tx: (Math.random() - 0.5) * 600,
      ty: -200 - Math.random() * 300,
      rot: Math.random() * 720 - 360,
      delay: Math.random() * 0.2,
      shape: Math.random() > 0.5 ? 'square' : 'rect',
    })),
  ).current;

  return (
    <div className="absolute inset-0 overflow-hidden pointer-events-none">
      {pieces.map((p) => (
        <span
          key={p.id}
          className="confetti-piece"
          style={
            {
              '--tx': `${p.tx}px`,
              '--ty': `${p.ty}px`,
              '--rot': `${p.rot}deg`,
              '--delay': `${p.delay}s`,
              left: '50%',
              top: '50%',
              width: p.shape === 'square' ? '8px' : '12px',
              height: '8px',
              background: `hsl(${p.hue}, 85%, 65%)`,
            } as React.CSSProperties
          }
        />
      ))}
      <style jsx>{`
        @keyframes confettiFly {
          0% { opacity: 1; transform: translate(-50%, -50%) rotate(0deg); }
          10% { opacity: 1; }
          100% {
            opacity: 0;
            transform: translate(calc(-50% + var(--tx)), calc(-50% + var(--ty) + 800px)) rotate(var(--rot));
          }
        }
        .confetti-piece {
          position: absolute;
          border-radius: 2px;
          animation: confettiFly 2.2s ease-out forwards;
          animation-delay: var(--delay);
          will-change: transform, opacity;
        }
        @media (prefers-reduced-motion: reduce) {
          .confetti-piece { animation: none; display: none; }
        }
      `}</style>
    </div>
  );
}
