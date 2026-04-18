'use client';

/**
 * Browse-based activation quest — 5 simple steps, triggered purely by visiting pages.
 * No purchase required, just exploration. Progress saved via /api/customer/activation.
 */

import Link from 'next/link';
import { useMemo, type ReactNode } from 'react';
import Icons from '@/components/SvgIcons';

export interface ActivationProgress {
  first_browse?: boolean;       // visited /products
  first_article?: boolean;      // visited any /articles/[slug]
  first_brand?: boolean;        // visited /about
  first_cart?: boolean;         // added to cart
  first_mascot?: boolean;       // visited /account/mascot
}

interface Step {
  key: keyof ActivationProgress;
  title: string;
  cta: string;
  href: string;
  icon: ReactNode;
}

const STEPS: Step[] = [
  { key: 'first_browse', title: '逛逛全館商品', cta: '去逛逛 →', href: '/products', icon: <Icons.ShoppingBag className="w-5 h-5 text-[#E0748C]" /> },
  { key: 'first_article', title: '看一篇仙女誌', cta: '去閱讀 →', href: '/articles', icon: <Icons.Leaf className="w-5 h-5 text-[#4A9D5F]" /> },
  { key: 'first_brand', title: '了解 FP 團隊', cta: '認識團隊 →', href: '/about', icon: <Icons.Ribbon className="w-5 h-5 text-[#E8A93B]" /> },
  { key: 'first_cart', title: '挑件心動商品', cta: '去選購 →', href: '/products', icon: <Icons.ShoppingBag className="w-5 h-5 text-[#9F6B3E]" /> },
  { key: 'first_mascot', title: '進入芽芽之家', cta: '去換裝 →', href: '/account/mascot', icon: <Icons.Seedling className="w-5 h-5 text-[#7BC47F]" /> },
];

export default function ActivationQuest({ progress }: { progress: ActivationProgress }) {
  const completed = useMemo(() => STEPS.filter((s) => progress[s.key]).length, [progress]);
  const total = STEPS.length;

  if (completed === total) return null;

  return (
    <div className="bg-white rounded-3xl border border-[#e7d9cb] shadow-sm overflow-hidden">
      <div className="px-5 pt-5 pb-3">
        <div className="flex items-center justify-between gap-2">
          <div>
            <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">仙女入門任務</div>
            <h3 className="text-base font-black text-slate-800 mt-0.5">{total} 步探索站內</h3>
          </div>
          <div className="text-[11px] font-black text-slate-400">
            {completed} / {total}
          </div>
        </div>
        <div className="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div
            className="h-full bg-gradient-to-r from-[#e7d9cb] to-[#9F6B3E] transition-all duration-500 rounded-full"
            style={{ width: `${(completed / total) * 100}%` }}
          />
        </div>
      </div>

      <div className="divide-y divide-slate-100">
        {STEPS.map((s) => {
          const done = !!progress[s.key];
          return done ? (
            <div key={s.key} className="px-5 py-3 flex items-center gap-3 bg-slate-50/50">
              <div className="w-9 h-9 rounded-xl bg-[#9F6B3E] flex items-center justify-center shrink-0 shadow-sm">
                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={3}>
                  <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <span className="text-sm font-black text-slate-400 line-through">{s.title}</span>
            </div>
          ) : (
            <Link
              key={s.key}
              href={s.href}
              className="px-5 py-3 flex items-center gap-3 active:bg-slate-50 transition-colors min-h-[56px]"
            >
              <div className="w-9 h-9 rounded-xl bg-[#e7d9cb]/50 flex items-center justify-center shrink-0">
                {s.icon}
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-sm font-black text-slate-800">{s.title}</div>
                <div className="text-[11px] font-bold text-[#7a5836]">{s.cta}</div>
              </div>
              <svg className="w-4 h-4 text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </Link>
          );
        })}
      </div>
    </div>
  );
}
