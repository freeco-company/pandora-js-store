'use client';

/**
 * Homepage mascot banner.
 * - Guest: login CTA
 * - Logged-in: mascot with EQUIPPED outfit + backdrop (connection feel) +
 *   progress bar toward next streak milestone + activation quest.
 * Tapping the mascot goes to /account/mascot for customization.
 */

import Link from 'next/link';
import { useEffect, useState } from 'react';
import Mascot from './Mascot';
import ActivationQuest, { type ActivationProgress } from './ActivationQuest';
import DodoNarrator, { type DodoMood } from './DodoNarrator';
import SiteIcon from '@/components/SiteIcon';
import { useAuth } from './AuthProvider';
import { getCustomerDashboard } from '@/lib/api';
import { stageFromStreak } from '@/lib/achievements';

export default function MascotBanner() {
  const { isLoggedIn, token, loading } = useAuth();
  const [progress, setProgress] = useState<ActivationProgress>({});
  const [streak, setStreak] = useState(0);
  const [outfit, setOutfit] = useState<string | null>(null);
  const [backdrop, setBackdrop] = useState<string | null>(null);
  const [achievementCount, setAchievementCount] = useState(0);

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    let cancelled = false;
    getCustomerDashboard(token)
      .then((d) => {
        if (cancelled) return;
        setProgress(d.customer.activation_progress || {});
        setStreak(d.customer.streak_days);
        setOutfit(d.customer.current_outfit);
        setBackdrop(d.customer.current_backdrop);
        setAchievementCount(d.achievements.earned.length);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [token, isLoggedIn]);

  // Fixed-height outer wrapper for ALL states — kills CLS when placeholder swaps
  // to real content or when guest → logged-in state changes.
  const WRAPPER_CLASSES = 'max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-8';
  const INNER_MIN_H = 'min-h-[320px] md:min-h-[340px]';

  if (loading) {
    return (
      <div aria-hidden className={WRAPPER_CLASSES}>
        <div className={`${INNER_MIN_H} rounded-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb]`} />
      </div>
    );
  }

  const stage = stageFromStreak(streak);

  // 朵朵 NPC 旁白（依登入 / streak / achievement 條件出對白）
  const dodo = pickDodoLine({ isLoggedIn, streak, achievementCount });

  // Next streak milestone
  const nextMilestone = streak < 7 ? 7 : streak < 30 ? 30 : streak < 100 ? 100 : 0;
  const prevMilestone = streak < 7 ? 0 : streak < 30 ? 7 : streak < 100 ? 30 : 100;
  const progressPct = nextMilestone
    ? Math.min(100, ((streak - prevMilestone) / (nextMilestone - prevMilestone)) * 100)
    : 100;

  return (
    <section className={WRAPPER_CLASSES}>
      <div className={`bg-gradient-to-br from-[#f7eee3] via-[#e7d9cb] to-[#f7eee3] rounded-3xl overflow-hidden relative shadow-sm ${INNER_MIN_H}`}>
        <div className="absolute -top-8 -right-8 w-40 h-40 rounded-full bg-white/20 blur-2xl" />
        <div className="absolute -bottom-8 -left-8 w-40 h-40 rounded-full bg-[#9F6B3E]/10 blur-2xl" />

        <div className="relative grid md:grid-cols-[auto_1fr] gap-6 p-5 sm:p-8 items-center">
          <Link
            href={isLoggedIn ? '/account/mascot' : '/account'}
            className="flex justify-center md:justify-start group"
            aria-label={isLoggedIn ? '進入芽芽之家' : '登入開始仙女任務'}
          >
            <div className="relative">
              <Mascot
                stage={stage}
                mood={isLoggedIn && streak >= 3 ? 'excited' : 'happy'}
                size={120}
                outfit={outfit}
                backdrop={backdrop}
                className="group-hover:scale-105 transition-transform duration-400"
              />
              {isLoggedIn && outfit && (
                <span className="absolute -bottom-1 left-1/2 -translate-x-1/2 px-2 py-0.5 bg-white/90 backdrop-blur rounded-full text-[9px] font-black text-[#9F6B3E] shadow-sm whitespace-nowrap">
                  <SiteIcon name="sparkle" size={12} className="inline" /> 穿戴中
                </span>
              )}
            </div>
          </Link>

          <div className="flex-1 min-w-0">
            <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-1">
              {isLoggedIn
                ? streak > 0 ? <><SiteIcon name="leaf" size={12} className="inline" /> 連續 {streak} 天</> : <><SiteIcon name="sprout" size={12} className="inline" /> 今日首訪</>
                : <><SiteIcon name="sprout" size={12} className="inline" /> 仙女任務</>}
            </div>
            <h2 className="text-xl sm:text-2xl font-black text-slate-800 mb-2">
              {isLoggedIn ? '哈囉仙女，今天想逛點什麼呢？' : '嗨～我是芽芽，要和我一起玩嗎？'}
            </h2>

            {/* 朵朵 NPC 旁白 — 依登入 / streak / 成就推播 */}
            {dodo && (
              <div className="mb-3">
                <DodoNarrator line={dodo.line} mood={dodo.mood} />
              </div>
            )}

            {/* Progress bar toward next streak milestone — only for logged-in */}
            {isLoggedIn && nextMilestone > 0 && (
              <div className="mb-4">
                <div className="flex items-center justify-between text-[11px] font-black mb-1">
                  <span className="text-[#9F6B3E]">下一個里程碑 <SiteIcon name="fire" size={12} className="inline" /> {nextMilestone} 天</span>
                  <span className="text-slate-500">{streak} / {nextMilestone}</span>
                </div>
                <div className="h-2 rounded-full bg-white/60 overflow-hidden">
                  <div
                    className="h-full bg-gradient-to-r from-[#e7d9cb] to-[#9F6B3E] rounded-full transition-all duration-700"
                    style={{ width: `${progressPct}%` }}
                  />
                </div>
              </div>
            )}

            {isLoggedIn && achievementCount > 0 && (
              <p className="text-[11px] font-bold text-slate-500 mb-3">
                已收集 <strong className="text-[#9F6B3E]">{achievementCount}</strong> 個成就
                {outfit ? <> · 穿戴中 <strong className="text-[#9F6B3E]">{outfit}</strong></> : null}
              </p>
            )}

            {!isLoggedIn && (
              <p className="text-sm text-slate-600 mb-4 leading-relaxed">
                登入後完成任務解鎖成就、服裝、芽芽的成長旅程，每日造訪還能連續 streak 獎勵
              </p>
            )}

            {isLoggedIn ? (
              <ActivationQuest progress={progress} />
            ) : (
              <div className="flex flex-wrap gap-3">
                <Link
                  href="/account"
                  className="inline-flex items-center px-5 py-2.5 bg-[#9F6B3E] text-white text-sm font-black rounded-full hover:bg-[#85572F] transition-colors min-h-[44px]"
                >
                  開始任務 →
                </Link>
                <Link
                  href="/products"
                  className="inline-flex items-center px-5 py-2.5 bg-white text-[#9F6B3E] text-sm font-black rounded-full border border-[#9F6B3E]/30 hover:bg-[#9F6B3E]/5 transition-colors min-h-[44px]"
                >
                  先逛商品
                </Link>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
}

/**
 * 朵朵 NPC 旁白挑選邏輯。
 * Voice 規則來源：docs/design/dodo-character-board-v1.md §5
 * - 妳 / 你 / 朋友（不寫「您」「會員」「用戶」）
 * - ≤ 25 字 / 行；不命令、不批判、不強迫
 */
function pickDodoLine({
  isLoggedIn,
  streak,
  achievementCount,
}: {
  isLoggedIn: boolean;
  streak: number;
  achievementCount: number;
}): { line: string; mood: DodoMood } | null {
  if (!isLoggedIn) {
    return { line: '我是朵朵。登入後我陪妳一起走。', mood: 'neutral' };
  }
  if (streak >= 100) {
    return { line: '一百天了。我會記得這個瞬間。', mood: 'cheering' };
  }
  if (streak >= 30) {
    return { line: '衝！妳已經比昨天的自己更靠近了。', mood: 'cheering' };
  }
  if (streak === 7) {
    return { line: '七天了，這個 milestone 值得記住。', mood: 'cheering' };
  }
  if (streak >= 8) {
    return { line: `連續 ${streak} 天了，妳又往前走一步。`, mood: 'happy' };
  }
  if (streak >= 1) {
    const left = 7 - streak;
    return { line: `已經 ${streak} 天，再 ${left} 天就到第一個里程碑。`, mood: 'happy' };
  }
  if (achievementCount > 0) {
    return { line: `妳收集了 ${achievementCount} 個成就，每一個我都記得。`, mood: 'happy' };
  }
  return { line: '今天慢慢來就好。我在這裡。', mood: 'neutral' };
}
