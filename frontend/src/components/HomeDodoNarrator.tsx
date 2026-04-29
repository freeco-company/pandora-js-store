'use client';

import { useEffect, useState } from 'react';
import DodoNarrator, { type DodoMood } from './DodoNarrator';
import { useAuth } from './AuthProvider';
import { getCustomerDashboard } from '@/lib/api';

interface Props {
  className?: string;
  size?: number;
}

/**
 * Hero 區塊內的朵朵 NPC 旁白。
 * Guest / 已登入皆顯示；對白依 streak / achievement 切換。
 */
export default function HomeDodoNarrator({ className = '', size = 56 }: Props) {
  const { isLoggedIn, token, loading } = useAuth();
  const [streak, setStreak] = useState(0);
  const [achievementCount, setAchievementCount] = useState(0);

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    let cancelled = false;
    getCustomerDashboard(token)
      .then((d) => {
        if (cancelled) return;
        setStreak(d.customer.streak_days);
        setAchievementCount(d.achievements.earned.length);
      })
      .catch(() => {});
    return () => {
      cancelled = true;
    };
  }, [token, isLoggedIn]);

  if (loading) return null;

  const dodo = pickDodoLine({ isLoggedIn, streak, achievementCount });
  if (!dodo) return null;

  return (
    <div className={className}>
      <DodoNarrator line={dodo.line} mood={dodo.mood} size={size} />
    </div>
  );
}

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
