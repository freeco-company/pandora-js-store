'use client';

/**
 * 寵物之家 — immersive full-screen mascot customization.
 *
 * Layout: mascot fills hero half; tabbed picker (outfit / backdrop) below.
 * Tapping a locked item → shows amber preview (cannot save).
 * Tapping an owned item → silver preview (save button lights up).
 * Save button commits via API and reloads dashboard.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import SiteIcon from '@/components/SiteIcon';
import OutfitIcon from '@/components/OutfitIcon';
import Mascot from '@/components/Mascot';
import LogoLoader from '@/components/LogoLoader';
import { badgeUrl } from '@/lib/badge';
import type { AchievementTier } from '@/lib/achievements';
import {
  getCustomerDashboard,
  setMascotOutfit,
  setMascotBackdrop,
  type CustomerDashboard,
} from '@/lib/api';
import { stageFromStreak } from '@/lib/achievements';

type Tab = 'outfit' | 'backdrop' | 'achievements';

export default function MascotHomePage() {
  const router = useRouter();
  const { token, isLoggedIn, loading } = useAuth();
  const [data, setData] = useState<CustomerDashboard | null>(null);
  const [tab, setTab] = useState<Tab>('outfit');

  // Preview state — null means no change from server state
  const [previewOutfit, setPreviewOutfit] = useState<string | null | undefined>(undefined);
  const [previewBackdrop, setPreviewBackdrop] = useState<string | null | undefined>(undefined);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!isLoggedIn && !loading) {
      router.replace('/account');
    }
  }, [isLoggedIn, loading, router]);

  useEffect(() => {
    if (!token || !isLoggedIn) return;
    getCustomerDashboard(token).then(setData).catch(() => {});
  }, [token, isLoggedIn]);

  if (loading || !data) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <LogoLoader size={96} />
      </div>
    );
  }

  const ownedCodes = new Set(data.outfits.owned.map((o) => o.code));
  const earnedCodes = new Set(data.achievements.earned.map((e) => e.code));
  const currentOutfit = previewOutfit !== undefined ? previewOutfit : data.customer.current_outfit;
  const currentBackdrop = previewBackdrop !== undefined ? previewBackdrop : data.customer.current_backdrop;
  const stage = stageFromStreak(data.customer.streak_days);

  const dirty =
    (previewOutfit !== undefined && previewOutfit !== data.customer.current_outfit) ||
    (previewBackdrop !== undefined && previewBackdrop !== data.customer.current_backdrop);

  const previewCanSave =
    (previewOutfit === undefined || previewOutfit === null || ownedCodes.has(previewOutfit)) &&
    (previewBackdrop === undefined || previewBackdrop === null || ownedCodes.has(previewBackdrop));

  const save = async () => {
    if (!token || !dirty || !previewCanSave || saving) return;
    setSaving(true);
    try {
      const tasks: Promise<unknown>[] = [];
      if (previewOutfit !== undefined) tasks.push(setMascotOutfit(token, previewOutfit));
      if (previewBackdrop !== undefined) tasks.push(setMascotBackdrop(token, previewBackdrop));
      await Promise.all(tasks);
      setPreviewOutfit(undefined);
      setPreviewBackdrop(undefined);
      // Re-fetch to confirm
      const fresh = await getCustomerDashboard(token);
      setData(fresh);
    } finally {
      setSaving(false);
    }
  };

  const reset = () => {
    setPreviewOutfit(undefined);
    setPreviewBackdrop(undefined);
  };

  return (
    <div className="min-h-[calc(100vh-64px)] flex flex-col">
      {/* Hero: full-bleed mascot with live backdrop */}
      <section
        className="relative flex-1 min-h-[40vh] sm:min-h-[50vh] flex items-center justify-center overflow-hidden"
        style={{
          background:
            'linear-gradient(180deg, #fdf7ef 0%, #f7eee3 50%, #e7d9cb 100%)',
        }}
      >
        {/* Ambient glow */}
        <div className="absolute -top-20 -right-20 w-80 h-80 rounded-full bg-[#f7c79a]/40 blur-3xl" />
        <div className="absolute -bottom-20 -left-20 w-80 h-80 rounded-full bg-[#9F6B3E]/20 blur-3xl" />

        {/* Top bar — back + title */}
        <div className="absolute top-0 left-0 right-0 p-4 flex items-center justify-between z-10">
          <Link
            href="/account"
            className="touch-target flex items-center justify-center w-10 h-10 rounded-full bg-white/70 backdrop-blur text-[#9F6B3E] hover:bg-white transition-colors"
            aria-label="返回仙女館"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
              <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
          </Link>
          <div className="text-center">
            <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E]">SPROUT HOME</div>
            <div className="text-sm font-black text-slate-700 flex items-center gap-1.5 mt-0.5">
              <SiteIcon name="fire" size={14} className="inline" /> 連續 {data.customer.streak_days} 天
            </div>
          </div>
          <div className="w-10" />
        </div>

        {/* Big mascot */}
        <div className="relative z-10">
          <Mascot
            stage={stage}
            mood={data.customer.streak_days >= 3 ? 'excited' : 'happy'}
            size={200}
            outfit={currentOutfit}
            backdrop={currentBackdrop}
          />
          {/* Speech bubble */}
          <div className="absolute -top-4 -right-10 bg-white rounded-2xl px-3 py-2 shadow-md border border-[#e7d9cb] text-[11px] font-black text-slate-700 mascot-wiggle">
            {dirty
              ? previewCanSave
                ? <><SiteIcon name="sparkle" size={14} className="inline" /> 好看呀～要留下來嗎？</>
                : <><SiteIcon name="lock" size={12} className="inline" /> 這件還沒解鎖呢</>
              : '嗨 ~ 今天想換什麼？'}
          </div>
        </div>

        <style jsx>{`
          @keyframes mascot-wiggle {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(-3deg); }
            75% { transform: rotate(3deg); }
          }
          .mascot-wiggle { animation: mascot-wiggle 3s ease-in-out infinite; transform-origin: bottom left; }
          @media (prefers-reduced-motion: reduce) {
            .mascot-wiggle { animation: none; }
          }
        `}</style>
      </section>

      {/* Save bar — appears when dirty */}
      {dirty && (
        <div className="sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] md:bottom-0 z-30 bg-white/95 backdrop-blur-md border-t border-[#e7d9cb] px-4 py-3 flex items-center gap-3 save-bar-in">
          <button
            onClick={reset}
            className="flex-1 sm:flex-none sm:min-w-[100px] py-3 rounded-full border border-[#e7d9cb] text-sm font-bold text-gray-600 hover:bg-slate-50 transition-colors"
          >
            取消
          </button>
          <button
            onClick={save}
            disabled={!previewCanSave || saving}
            className={`flex-1 py-3 rounded-full text-sm font-black transition-all ${
              previewCanSave
                ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] text-white shadow-lg shadow-[#9F6B3E]/30'
                : 'bg-slate-200 text-slate-400 cursor-not-allowed'
            }`}
          >
            {saving ? '儲存中…' : previewCanSave ? <><SiteIcon name="sparkle" size={14} className="inline" /> 儲存裝扮</> : <><SiteIcon name="lock" size={14} className="inline" /> 未解鎖</>}
          </button>
          <style jsx>{`
            @keyframes save-bar-in {
              from { transform: translateY(100%); opacity: 0; }
              to { transform: translateY(0); opacity: 1; }
            }
            .save-bar-in { animation: save-bar-in 0.3s cubic-bezier(0.2, 0.9, 0.3, 1.1) forwards; }
          `}</style>
        </div>
      )}

      {/* Tabs */}
      <div className="bg-white border-t border-[#e7d9cb]">
        <div className="max-w-2xl mx-auto px-4 sm:px-6">
          <div className="flex gap-1 pt-4">
            {(['outfit', 'backdrop', 'achievements'] as const).map((t) => {
              const active = tab === t;
              const label = t === 'outfit' ? <><SiteIcon name="ribbon" size={14} className="inline" /> 服裝</> : t === 'backdrop' ? <><SiteIcon name="sparkle" size={14} className="inline" /> 背景</> : <><SiteIcon name="trophy" size={14} className="inline" /> 成就</>;
              let ownedCount: number, total: number;
              if (t === 'outfit') {
                ownedCount = Object.keys(data.outfits.catalog).filter((c) => ownedCodes.has(c)).length;
                total = Object.keys(data.outfits.catalog).length;
              } else if (t === 'backdrop') {
                ownedCount = Object.keys(data.outfits.backdrops).filter((c) => ownedCodes.has(c)).length;
                total = Object.keys(data.outfits.backdrops).length;
              } else {
                ownedCount = data.achievements.earned.length;
                total = Object.keys(data.achievements.catalog).length;
              }
              return (
                <button
                  key={t}
                  onClick={() => setTab(t)}
                  className={`flex-1 py-3 rounded-t-2xl text-sm font-black transition-colors relative ${
                    active
                      ? 'text-[#9F6B3E] bg-[#fdf7ef]'
                      : 'text-slate-400 hover:text-slate-600'
                  }`}
                >
                  {label}
                  <span className="ml-1.5 text-[10px] font-bold opacity-60">
                    {ownedCount}/{total}
                  </span>
                  {active && <span className="absolute bottom-0 left-4 right-4 h-0.5 bg-[#9F6B3E] rounded-full" />}
                </button>
              );
            })}
          </div>
        </div>
      </div>

      {/* Picker grid */}
      <section className="bg-[#fdf7ef] px-4 sm:px-6 pt-5 pb-8">
        <div className="max-w-2xl mx-auto">
          {tab === 'outfit' && (
            <PickerGrid
              items={[
                { code: '__none__', meta: { name: '不穿戴', emoji: 'no-entry' }, owned: true, previewValue: null },
                ...Object.entries(data.outfits.catalog).map(([code, meta]) => ({
                  code,
                  meta,
                  owned: ownedCodes.has(code),
                  previewValue: code,
                })),
              ]}
              current={currentOutfit}
              progressFor={(meta) => outfitProgress(meta, data)}
              onTap={(v) => setPreviewOutfit(v)}
            />
          )}
          {tab === 'backdrop' && (
            <PickerGrid
              items={[
                { code: '__none__', meta: { name: '素雅', emoji: 'no-entry' }, owned: true, previewValue: null },
                ...Object.entries(data.outfits.backdrops).map(([code, meta]) => ({
                  code,
                  meta,
                  owned: ownedCodes.has(code),
                  previewValue: code,
                })),
              ]}
              current={currentBackdrop}
              progressFor={(meta) => outfitProgress(meta, data)}
              onTap={(v) => setPreviewBackdrop(v)}
            />
          )}

          {tab === 'achievements' && (
            <AchievementsGrid
              catalog={data.achievements.catalog}
              earnedCodes={earnedCodes}
              progress={data.achievements.progress}
            />
          )}
        </div>
      </section>
    </div>
  );
}

interface PickerItem {
  code: string;
  meta: { name: string; emoji: string; unlock?: { type: string; value: number } };
  owned: boolean;
  previewValue: string | null;
}

/**
 * Compute current/target progress for an outfit/backdrop unlock requirement.
 * Returns null for the "no item" entries that have no unlock spec.
 * Reuses the dashboard payload — no extra API call.
 */
function outfitProgress(
  meta: { unlock?: { type: string; value: number } },
  data: CustomerDashboard,
): { current: number; target: number } | null {
  if (!meta.unlock) return null;
  const target = meta.unlock.value;
  const raw = (() => {
    switch (meta.unlock.type) {
      case 'orders': return data.customer.total_orders;
      case 'spend': return data.customer.total_spent;
      case 'streak': return data.customer.streak_days;
      case 'achievements': return data.achievements.earned.length;
      default: return 0;
    }
  })();
  return { current: Math.min(raw, target), target };
}

/** Inline progress bar — small enough to sit inside a square tile. */
function ProgressBar({ current, target }: { current: number; target: number }) {
  const pct = target > 0 ? Math.min(100, (current / target) * 100) : 0;
  const near = pct >= 66 && pct < 100;
  return (
    <div className="w-full">
      <div className="h-1 rounded-full bg-slate-200 overflow-hidden">
        <div
          className={`h-full transition-all duration-500 ${near ? 'bg-[#c0392b]' : 'bg-[#9F6B3E]'}`}
          style={{ width: `${pct}%` }}
        />
      </div>
    </div>
  );
}

function PickerGrid({
  items,
  current,
  progressFor,
  onTap,
}: {
  items: PickerItem[];
  current: string | null | undefined;
  progressFor?: (meta: PickerItem['meta']) => { current: number; target: number } | null;
  onTap: (v: string | null) => void;
}) {
  return (
    <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
      {items.map((item) => {
        const isCurrent = current === item.previewValue;
        const prog = !item.owned && progressFor ? progressFor(item.meta) : null;
        const unlockText = item.meta.unlock
          ? item.meta.unlock.type === 'orders' ? `${item.meta.unlock.value} 筆訂單`
          : item.meta.unlock.type === 'spend' ? `消費 $${item.meta.unlock.value.toLocaleString()}`
          : item.meta.unlock.type === 'streak' ? `連登 ${item.meta.unlock.value} 天`
          : item.meta.unlock.type === 'achievements' ? `${item.meta.unlock.value} 個成就`
          : ''
          : '';
        const progressText = prog
          ? item.meta.unlock?.type === 'spend'
            ? `$${prog.current.toLocaleString()}/$${prog.target.toLocaleString()}`
            : `${prog.current}/${prog.target}`
          : '';

        return (
          <button
            key={item.code}
            onClick={() => onTap(item.previewValue)}
            className={`relative aspect-square rounded-2xl p-2 flex flex-col items-center justify-center gap-1 text-center transition-all ring-2 ${
              isCurrent
                ? 'bg-gradient-to-br from-[#9F6B3E] to-[#85572F] ring-[#9F6B3E] text-white shadow-lg shadow-[#9F6B3E]/30 scale-[1.05]'
                : item.owned
                ? 'bg-white ring-[#e7d9cb] hover:ring-[#9F6B3E]/50 hover:-translate-y-0.5'
                : 'bg-slate-50 ring-amber-200 opacity-70'
            }`}
            aria-label={
              item.owned
                ? isCurrent
                  ? `${item.meta.name}（目前預覽）`
                  : `預覽 ${item.meta.name}`
                : `${item.meta.name}（未解鎖 — ${progressText || unlockText}）`
            }
          >
            {!item.owned && (
              <span className="absolute top-1 right-1 w-5 h-5 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-[10px] font-black">
                <SiteIcon name="lock" size={14} />
              </span>
            )}
            {isCurrent && (
              <span className="absolute top-1 left-1 text-[8px] font-black px-1.5 py-0.5 rounded-full bg-white/30 text-white leading-none">
                預覽中
              </span>
            )}
            <span className={`text-2xl sm:text-3xl ${!item.owned ? 'grayscale opacity-60' : ''}`}>
              {item.owned
                ? <OutfitIcon name={item.meta.emoji} size={28} static={!item.owned} />
                : <SiteIcon name="lock" size={24} />}
            </span>
            <span className={`text-[10px] font-black leading-tight ${isCurrent ? 'text-white' : item.owned ? 'text-slate-800' : 'text-slate-400'}`}>
              {item.meta.name}
            </span>
            {!item.owned && (progressText || unlockText) && (
              <span className="text-[8px] font-bold text-slate-500 leading-tight">
                {progressText || unlockText}
              </span>
            )}
            {prog && <ProgressBar current={prog.current} target={prog.target} />}
          </button>
        );
      })}
    </div>
  );
}

/**
 * Achievements grid — re-orders so "near completion" unearned achievements
 * surface first, then other unearned, then earned at the bottom.
 * The reordering is a soft motivational nudge: the user sees what's
 * within reach immediately.
 */
function AchievementsGrid({
  catalog,
  earnedCodes,
  progress,
}: {
  catalog: CustomerDashboard['achievements']['catalog'];
  earnedCodes: Set<string>;
  progress: CustomerDashboard['achievements']['progress'];
}) {
  const entries = Object.entries(catalog);

  const sorted = [...entries].sort(([codeA, defA], [codeB, defB]) => {
    const earnedA = earnedCodes.has(codeA);
    const earnedB = earnedCodes.has(codeB);
    if (earnedA !== earnedB) return earnedA ? 1 : -1; // unearned first

    // Both unearned — sort by progress desc (closer to done = higher in list)
    if (!earnedA && !earnedB) {
      const pctA = progress[codeA] ? progress[codeA].current / progress[codeA].target : -1;
      const pctB = progress[codeB] ? progress[codeB].current / progress[codeB].target : -1;
      if (pctA !== pctB) return pctB - pctA;
    }
    // Stable fallback by tier importance: gold > silver > bronze
    const rank = { gold: 0, silver: 1, bronze: 2 } as Record<string, number>;
    return (rank[defA.tier] ?? 9) - (rank[defB.tier] ?? 9);
  });

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
      {sorted.map(([code, def]) => {
        const earned = earnedCodes.has(code);
        const prog = !earned ? progress[code] : null;
        const near = prog && prog.target > 0 && prog.current / prog.target >= 0.66 && prog.current < prog.target;

        return (
          <div
            key={code}
            title={def.description}
            className={`relative rounded-2xl p-3 flex flex-col items-center text-center gap-1.5 transition-all ${
              earned
                ? 'bg-white border border-[#e7d9cb] shadow-sm'
                : near
                ? 'bg-gradient-to-br from-[#fdf3ec] to-white border-2 border-[#c0392b]/40 shadow-sm'
                : 'bg-slate-100 opacity-70'
            }`}
          >
            {near && (
              <span className="absolute -top-2 -right-1 px-2 py-0.5 rounded-full bg-[#c0392b] text-white text-[9px] font-black shadow-md">
                快達成
              </span>
            )}
            {earned && (
              <img
                src={badgeUrl(code, def.tier as AchievementTier)}
                alt=""
                width={28}
                height={28}
                aria-hidden
                className="absolute -top-1 -left-1 drop-shadow-sm"
              />
            )}
            <div className={`mb-0.5 ${earned ? '' : 'grayscale opacity-70'}`}>
              <OutfitIcon name={def.emoji} size={32} static={!earned} />
            </div>
            <div className={`text-[11px] font-black leading-tight ${earned ? 'text-slate-800' : 'text-slate-600'}`}>
              {def.name}
            </div>
            <div className="text-[10px] text-slate-500 leading-tight line-clamp-2 px-1">
              {def.description}
            </div>
            {prog && (
              <div className="w-full mt-auto pt-1 space-y-1">
                <div className="text-[9px] font-black text-[#9F6B3E]">
                  {def.progress?.type === 'spend_total'
                    ? `$${prog.current.toLocaleString()} / $${prog.target.toLocaleString()}`
                    : `${prog.current} / ${prog.target}`}
                </div>
                <ProgressBar current={prog.current} target={prog.target} />
              </div>
            )}
            {earned && (
              <div className="text-[9px] font-black text-[#9F6B3E] mt-auto pt-1">
                已達成
              </div>
            )}
          </div>
        );
      })}
    </div>
  );
}
