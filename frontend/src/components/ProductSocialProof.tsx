'use client';

/**
 * Real social proof — shows cumulative sales + recent viewers below the
 * product title. Both numbers come straight from DB (no fabrication):
 *   - total_sold: sum(qty) across paid orders historically
 *   - viewers_now: distinct sessions in the last 15 min
 *
 * The endpoint hides numbers below thresholds so brand-new products
 * stay quiet (no embarrassing "已售 0 件"). Component renders nothing
 * when both values are null.
 */

import { useEffect, useState } from 'react';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface SocialProofData {
  total_sold: number | null;
  viewers_now: number | null;
  window_min: number;
}

export default function ProductSocialProof({ slug }: { slug: string }) {
  const [data, setData] = useState<SocialProofData | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetch(`${API_URL}/products/${encodeURIComponent(slug)}/social-proof`, {
      cache: 'no-store',
    })
      .then((r) => r.ok ? r.json() : null)
      .then((j: SocialProofData | null) => { if (!cancelled && j) setData(j); })
      .catch(() => {});
    return () => { cancelled = true; };
  }, [slug]);

  if (!data) return null;
  if (data.total_sold == null && data.viewers_now == null) return null;

  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500 mb-3">
      {data.total_sold != null && (
        <span className="inline-flex items-center gap-1">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} className="text-[#9F6B3E]" aria-hidden>
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
          </svg>
          累積售出 <strong className="text-gray-700 tabular-nums">{data.total_sold.toLocaleString()}</strong> 件
        </span>
      )}
      {data.viewers_now != null && (
        <>
          {data.total_sold != null && <span className="text-gray-300" aria-hidden>·</span>}
          <span className="inline-flex items-center gap-1">
            <span className="relative flex h-1.5 w-1.5" aria-hidden>
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#c0392b] opacity-60" />
              <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-[#c0392b]" />
            </span>
            近 {data.window_min} 分鐘內 <strong className="text-gray-700 tabular-nums">{data.viewers_now}</strong> 位仙女在看
          </span>
        </>
      )}
    </div>
  );
}
