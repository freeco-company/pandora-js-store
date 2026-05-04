'use client';

/**
 * SPEC-cross-app-streak Phase 1.C — per-App 每日連續登入 streak 提示（母艦）。
 *
 * App boot 後（user logged in）一次 fetch /api/streak/today：
 *   - is_first_today=true 且非 milestone → 3 秒小 toast「連續第 N 天 🔥」
 *   - is_milestone=true → 5 秒大 toast + 朵朵語氣文案「妳已經連續 N 天了，朋友！」
 *   - is_first_today=false → 不顯示
 *
 * 文案規範（集團 group-naming-and-voice.md）：
 *   - 用「妳/你 / 朋友」不用「您」
 *   - 不寫「集團」改寫「FP 團隊」
 *   - 朵朵 = 導師 NPC
 *   - 不出現減脂/燃脂/排毒等紅線詞
 *
 * 鏡像 pandora-meal/frontend/public/streak-toast.js（vanilla），但本檔
 * 是 React component 因為母艦 frontend 是 Next.js 16 + React 19。
 */

import { useEffect, useRef, useState } from 'react';
import { useAuth } from './AuthProvider';
import { API_URL } from '@/lib/api';

interface StreakSnapshot {
  current_streak: number;
  longest_streak: number;
  is_first_today: boolean;
  is_milestone: boolean;
  milestone_label: string | null;
  today_date: string;
}

const SHOWN_KEY_PREFIX = 'pandora-streak-shown-';

export default function StreakToast() {
  const { token, isLoggedIn } = useAuth();
  const fetchedRef = useRef<string | null>(null);
  const [snap, setSnap] = useState<StreakSnapshot | null>(null);
  const [exiting, setExiting] = useState(false);

  useEffect(() => {
    if (!isLoggedIn || !token) return;
    // de-dupe — token + same-day → only fetch once per session.
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

        // localStorage guard — even if fetchedRef misses (e.g. hot reload),
        // don't double-show on the same calendar date.
        const seenKey = `${SHOWN_KEY_PREFIX}${data.today_date}`;
        if (typeof window !== 'undefined' && localStorage.getItem(seenKey) === '1') {
          return;
        }
        if (typeof window !== 'undefined') {
          localStorage.setItem(seenKey, '1');
        }

        setSnap(data);

        const duration = data.is_milestone ? 5000 : 3000;
        setTimeout(() => setExiting(true), duration);
        setTimeout(() => setSnap(null), duration + 300);
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
  const headline = isMilestone
    ? `妳已經連續 ${snap.current_streak} 天了，朋友！`
    : `連續第 ${snap.current_streak} 天 🔥`;
  const sub = isMilestone
    ? '朵朵看見了，繼續保持下去 ✨'
    : null;

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
      </div>
    </div>
  );
}
