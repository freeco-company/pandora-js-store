'use client';

/**
 * SPEC-cross-app-streak Phase 1.C + SPEC-streak-milestone-rewards (母艦)
 *
 * App boot 後（user logged in）一次 fetch /api/streak/today：
 *   - is_first_today=true 且非 milestone → 3 秒小 toast「連續第 N 天 🔥」
 *   - is_milestone=true → 5 秒大 toast + 朵朵語氣文案
 *     + 解鎖 chip（成就徽章 + 個人化折扣券，若有）
 *   - 21 / 100 天 → 全屏 overlay tap-to-dismiss
 *   - is_first_today=false → 不顯示
 *
 * 文案規範（集團 group-naming-and-voice.md）：
 *   - 用「妳/你 / 朋友」不用「您」
 *   - 不寫「集團」「會員」改寫「老朋友」「FP 團隊」
 *   - 朵朵 = 導師 NPC
 *   - 不出現減脂/燃脂/排毒/治療等紅線詞
 *
 * 鏡像 pandora-meal/frontend/public/streak-toast.js（vanilla），但本檔
 * 是 React component 因為母艦 frontend 是 Next.js 16 + React 19。
 */

import { useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthProvider';
import { API_URL } from '@/lib/api';

interface StreakUnlocks {
  streak_days: number;
  achievements_awarded: string[];
  coupon_code: string | null;
  coupon_value: number | null;
  coupon_label: string | null;
  already_unlocked: boolean;
}

interface StreakSnapshot {
  current_streak: number;
  longest_streak: number;
  is_first_today: boolean;
  is_milestone: boolean;
  milestone_label: string | null;
  today_date: string;
  unlocks: StreakUnlocks | null;
}

const SHOWN_KEY_PREFIX = 'pandora-streak-shown-';
const FULLSCREEN_MILESTONES = new Set([21, 100]);

function milestoneHeadline(streak: number): string {
  if (streak === 1) return '回來看朵朵的第一天，朋友！';
  if (streak === 100) return '妳已經連續 100 天了，朋友！';
  if (streak === 21) return '21 天習慣養成達成！';
  return `妳已經連續 ${streak} 天了，朋友！`;
}

function milestoneSub(streak: number): string {
  if (streak === 1) return '朵朵會在這裡陪妳一陣子 ✨';
  if (streak === 7) return '一週的老朋友了，謝謝妳沒走開';
  if (streak === 21) return '習慣已經內建到妳的日子裡了';
  if (streak === 60) return '兩個月的好朋友，朵朵都記得';
  if (streak === 100) return '百日傳奇，朵朵以妳為傲';
  return '朵朵看見了，繼續保持下去';
}

export default function StreakToast() {
  const { token, isLoggedIn } = useAuth();
  const fetchedRef = useRef<string | null>(null);
  const [snap, setSnap] = useState<StreakSnapshot | null>(null);
  const [exiting, setExiting] = useState(false);

  useEffect(() => {
    if (!isLoggedIn || !token) return;
    if (fetchedRef.current === token) return;
    fetchedRef.current = token;

    let cancelled = false;
    (async () => {
      try {
        const res = await fetch(`${API_URL}/streak/today`, {
          headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
          },
          signal: AbortSignal.timeout(8_000),
        });
        if (!res.ok) return;
        const data: StreakSnapshot = await res.json();
        if (cancelled) return;
        if (!data.is_first_today) return;

        const seenKey = `${SHOWN_KEY_PREFIX}${data.today_date}`;
        if (typeof window !== 'undefined' && localStorage.getItem(seenKey) === '1') {
          return;
        }
        if (typeof window !== 'undefined') {
          localStorage.setItem(seenKey, '1');
        }

        setSnap(data);

        const isFullscreen = data.is_milestone && FULLSCREEN_MILESTONES.has(data.current_streak);
        // Fullscreen overlays wait for user tap; only auto-dismiss small/medium toasts.
        if (!isFullscreen) {
          const duration = data.is_milestone ? 5000 : 3000;
          setTimeout(() => setExiting(true), duration);
          setTimeout(() => setSnap(null), duration + 300);
        }
      } catch {
        // network error / timeout — silently ignore, streak toast is non-critical
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [isLoggedIn, token]);

  if (!snap) return null;

  const isMilestone = snap.is_milestone;
  const isFullscreen = isMilestone && FULLSCREEN_MILESTONES.has(snap.current_streak);

  if (isFullscreen) {
    return (
      <div
        role="dialog"
        aria-modal="true"
        aria-label="連續登入里程碑"
        className="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 backdrop-blur-sm"
        onClick={() => setSnap(null)}
      >
        <div
          className="mx-6 max-w-md rounded-3xl bg-white px-8 py-10 text-center shadow-2xl"
          onClick={(e) => e.stopPropagation()}
        >
          <div className="mb-4 text-5xl" aria-hidden="true">
            ✨
          </div>
          <h2 className="mb-2 text-2xl font-bold text-[#9F6B3E]">
            {milestoneHeadline(snap.current_streak)}
          </h2>
          <p className="mb-6 text-sm text-[#b09070]">{milestoneSub(snap.current_streak)}</p>
          {snap.unlocks && <UnlockChips unlocks={snap.unlocks} />}
          <button
            type="button"
            onClick={() => setSnap(null)}
            className="mt-6 rounded-full bg-[#9F6B3E] px-6 py-2 text-sm font-semibold text-white shadow-md hover:bg-[#8a5b34]"
          >
            繼續逛逛
          </button>
        </div>
      </div>
    );
  }

  const headline = isMilestone
    ? milestoneHeadline(snap.current_streak)
    : `連續第 ${snap.current_streak} 天 🔥`;
  const sub = isMilestone ? milestoneSub(snap.current_streak) : null;

  return (
    <div
      role="status"
      aria-live="polite"
      className={`pointer-events-none fixed left-1/2 top-20 z-[60] -translate-x-1/2 transition-all duration-300 ease-out ${
        exiting ? 'opacity-0 -translate-y-4' : 'opacity-100 translate-y-0'
      }`}
    >
      <div
        className={`pointer-events-auto rounded-2xl border-l-4 border-l-[#9F6B3E] bg-white px-5 py-3 shadow-lg ${
          isMilestone ? 'min-w-[280px]' : 'min-w-[200px]'
        }`}
      >
        <p className="text-sm font-semibold text-[#9F6B3E]">{headline}</p>
        {sub && <p className="mt-1 text-xs text-[#b09070]">{sub}</p>}
        {isMilestone && snap.unlocks && (
          <div className="mt-2">
            <UnlockChips unlocks={snap.unlocks} compact />
          </div>
        )}
      </div>
    </div>
  );
}

/**
 * Renders reward chips: achievement badge label + (if present) coupon code.
 * `compact` shrinks for inline toast use; default is fullscreen-overlay sized.
 */
function UnlockChips({
  unlocks,
  compact = false,
}: {
  unlocks: StreakUnlocks;
  compact?: boolean;
}) {
  const hasAchievement = unlocks.achievements_awarded.length > 0;
  const hasCoupon = !!unlocks.coupon_code;
  if (!hasAchievement && !hasCoupon) return null;

  return (
    <div className={`flex flex-wrap items-center justify-center gap-2 ${compact ? 'mt-1' : 'mt-2'}`}>
      {hasAchievement && (
        <span
          className={`inline-flex items-center rounded-full bg-[#f5ead8] px-3 py-1 font-medium text-[#9F6B3E] ${
            compact ? 'text-[11px]' : 'text-xs'
          }`}
        >
          🏅 解鎖徽章
        </span>
      )}
      {hasCoupon && (
        <span
          className={`inline-flex flex-col items-center rounded-xl bg-[#9F6B3E] px-3 py-1 font-semibold text-white ${
            compact ? 'text-[11px]' : 'text-xs'
          }`}
        >
          <span>🎁 NT${unlocks.coupon_value} 折扣券</span>
          <span className="font-mono text-[10px] opacity-90">{unlocks.coupon_code}</span>
        </span>
      )}
    </div>
  );
}
