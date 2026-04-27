'use client';

import { useEffect, useState } from 'react';
import { API_URL, fetchApi } from '@/lib/api';
import SiteIcon from '@/components/SiteIcon';

type Status = {
  order_number: string;
  status: string;
  payment_method: string | null;
  shipping_method: string | null;
  confirmed_at: string | null;
  line_bound: boolean;
  needs_line_confirmation: boolean;
};

/**
 * COD + 超商取貨訂單需在 LINE 上點按鈕確認後才出貨。
 *
 * 三個狀態 UI：
 *   1. needs_line_confirmation && !line_bound → 顯示「加 LINE 確認出貨」CTA（必走）
 *   2. needs_line_confirmation && line_bound → 顯示「請至 LINE 點按鈕確認」+ 輪詢
 *   3. !needs_line_confirmation → 顯示「✅ 已確認，準備出貨」
 *
 * Token 取自 sessionStorage（checkout 頁存的）或 URL（OAuth 回拋時不帶，需從 storage 拿）。
 */
export default function CodLineConfirmation({
  orderNumber,
  justBound,
}: {
  orderNumber: string;
  justBound: boolean;
}) {
  const [status, setStatus] = useState<Status | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [token, setToken] = useState<string | null>(null);

  useEffect(() => {
    try {
      setToken(sessionStorage.getItem(`cod_token_${orderNumber}`));
    } catch {
      setToken(null);
    }
  }, [orderNumber]);

  // Fetch status — gated by token. Re-poll every 6s while pending_confirmation
  // (so the UI flips to "已確認" within ~6s of the customer pressing the LINE button).
  useEffect(() => {
    if (!token) return;
    let stopped = false;

    const poll = async () => {
      try {
        const s = await fetchApi<Status>(
          `/orders/${encodeURIComponent(orderNumber)}/confirmation-status?token=${encodeURIComponent(token)}`
        );
        if (stopped) return;
        setStatus(s);
        // 確認完成 / 取消 → 停止輪詢
        if (s.status !== 'pending_confirmation') {
          // 安全清掉 token（已不再需要）
          try { sessionStorage.removeItem(`cod_token_${orderNumber}`); } catch {}
          return;
        }
        setTimeout(poll, 6000);
      } catch (e: any) {
        if (stopped) return;
        setError(e?.message || '查詢訂單狀態失敗');
      }
    };

    poll();
    return () => { stopped = true; };
  }, [token, orderNumber]);

  // No token in storage — likely a refresh after the COD bind already done,
  // or a guest copy-pasting the URL. Hide the component (the rest of the page
  // already shows order success).
  if (!token && !justBound) return null;

  if (error) {
    return (
      <div className="bg-white rounded-3xl border-2 border-red-200 p-5 sm:p-7">
        <p className="text-sm text-red-600">{error}</p>
      </div>
    );
  }

  if (!status) {
    return (
      <div className="bg-white rounded-3xl border border-[#e7d9cb] p-5 sm:p-7 text-center text-sm text-slate-500">
        正在查詢訂單狀態…
      </div>
    );
  }

  // 已確認 — 出貨中
  if (!status.needs_line_confirmation) {
    if (status.status === 'cancelled') {
      return (
        <div className="bg-white rounded-3xl border-2 border-red-200 p-5 sm:p-7">
          <h3 className="text-base font-black text-red-600 mb-1">訂單已取消</h3>
          <p className="text-sm text-slate-600">本訂單因未在 48 小時內完成 LINE 確認，已自動取消。如仍要購買請重新下單，謝謝 🙏</p>
        </div>
      );
    }
    return (
      <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-3xl border-2 border-green-300 p-5 sm:p-7">
        <div className="flex items-start gap-3">
          <div className="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center shrink-0">
            <SiteIcon name="check-circle" size={22} className="text-white" />
          </div>
          <div>
            <h3 className="text-base font-black text-green-800">訂單已確認 · 準備出貨</h3>
            <p className="text-xs text-green-700 mt-1 leading-relaxed">
              感謝您的確認！我們將於 3 個工作天內為您安排出貨，超商到貨後會以 LINE 通知您。
            </p>
          </div>
        </div>
      </div>
    );
  }

  // 強制 LINE 確認流程
  const lineBindUrl = `${API_URL}/auth/line?intent=bind-order&order=${encodeURIComponent(orderNumber)}&token=${encodeURIComponent(token!)}`;

  return (
    <div className="relative bg-white rounded-3xl border-2 border-[#9F6B3E] p-5 sm:p-7 shadow-lg shadow-[#9F6B3E]/10">
      {/* 醒目標籤 */}
      <div className="absolute -top-3 left-5 bg-[#9F6B3E] text-white text-[10px] font-black tracking-[0.2em] px-3 py-1 rounded-full">
        重要 · 請完成此步驟
      </div>

      <div className="flex items-start gap-3 mb-4 mt-2">
        <div className="w-10 h-10 rounded-full bg-[#06C755] flex items-center justify-center shrink-0">
          {/* LINE icon */}
          <svg viewBox="0 0 24 24" width="22" height="22" fill="white" aria-hidden>
            <path d="M19.365 9.89c.50 0 .806.402.806.804 0 .402-.305.806-.806.806h-2.215v1.41h2.215c.5 0 .806.402.806.806 0 .404-.305.806-.806.806h-3.02c-.402 0-.806-.404-.806-.806V7.578c0-.402.404-.806.806-.806h3.02c.5 0 .806.404.806.806 0 .402-.305.806-.806.806h-2.215v1.51h2.215zm-4.83 3.83c0 .35-.25.65-.6.75-.05.05-.15.05-.2.05-.25 0-.5-.1-.65-.3l-3.1-4.2v3.7c0 .4-.4.8-.8.8-.45 0-.85-.4-.85-.8V7.6c0-.35.25-.65.6-.75.05-.05.15-.05.2-.05.25 0 .5.1.65.3l3.15 4.2V7.6c0-.4.35-.8.8-.8.4 0 .8.4.8.8v6.12zm-7.6.85c-.45 0-.8-.4-.8-.8V7.6c0-.4.35-.8.8-.8.4 0 .8.4.8.8v6.16c0 .4-.4.8-.8.8zm-2.4 0H1.51c-.4 0-.8-.4-.8-.8V7.6c0-.4.4-.8.8-.8.45 0 .85.4.85.8v5.36h2.165c.4 0 .8.4.8.806 0 .404-.4.804-.8.804zM24 10.31C24 4.62 18.62 0 12 0S0 4.62 0 10.31c0 5.099 4.265 9.371 10.025 10.181.39.085.92.255 1.055.585.119.299.078.768.038 1.069l-.17 1.024c-.054.299-.244 1.169 1.024.639 1.27-.53 6.819-4.014 9.31-6.879C22.98 14.93 24 12.751 24 10.31z" />
          </svg>
        </div>
        <div>
          <h3 className="text-base font-black text-slate-800">請加入 LINE 並確認出貨</h3>
          <p className="text-xs text-slate-500 mt-0.5">完成此步驟後我們才會安排出貨</p>
        </div>
      </div>

      {!status.line_bound && !justBound ? (
        <>
          <ol className="space-y-2 mb-5 text-sm text-slate-700">
            <li className="flex gap-2">
              <span className="w-5 h-5 rounded-full bg-[#9F6B3E] text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">1</span>
              <span>點擊下方按鈕，加入「婕樂纖仙女館」LINE 官方帳號</span>
            </li>
            <li className="flex gap-2">
              <span className="w-5 h-5 rounded-full bg-[#9F6B3E] text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">2</span>
              <span>系統會在 LINE 推送「確認出貨」訊息</span>
            </li>
            <li className="flex gap-2">
              <span className="w-5 h-5 rounded-full bg-[#9F6B3E] text-white text-[11px] font-black flex items-center justify-center shrink-0 mt-0.5">3</span>
              <span>點訊息上的「✅ 確認出貨」按鈕，即完成下單</span>
            </li>
          </ol>

          <a
            href={lineBindUrl}
            className="block w-full text-center py-3.5 rounded-full bg-[#06C755] hover:bg-[#05b04c] text-white font-black text-sm shadow-lg shadow-[#06C755]/30 transition-colors min-h-[52px] flex items-center justify-center gap-2"
          >
            <svg viewBox="0 0 24 24" width="20" height="20" fill="white" aria-hidden>
              <path d="M19.365 9.89c.50 0 .806.402.806.804 0 .402-.305.806-.806.806h-2.215v1.41h2.215c.5 0 .806.402.806.806 0 .404-.305.806-.806.806h-3.02c-.402 0-.806-.404-.806-.806V7.578c0-.402.404-.806.806-.806h3.02c.5 0 .806.404.806.806 0 .402-.305.806-.806.806h-2.215v1.51h2.215zm-4.83 3.83c0 .35-.25.65-.6.75-.05.05-.15.05-.2.05-.25 0-.5-.1-.65-.3l-3.1-4.2v3.7c0 .4-.4.8-.8.8-.45 0-.85-.4-.85-.8V7.6c0-.35.25-.65.6-.75.05-.05.15-.05.2-.05.25 0 .5.1.65.3l3.15 4.2V7.6c0-.4.35-.8.8-.8.4 0 .8.4.8.8v6.12zm-7.6.85c-.45 0-.8-.4-.8-.8V7.6c0-.4.35-.8.8-.8.4 0 .8.4.8.8v6.16c0 .4-.4.8-.8.8zm-2.4 0H1.51c-.4 0-.8-.4-.8-.8V7.6c0-.4.4-.8.8-.8.45 0 .85.4.85.8v5.36h2.165c.4 0 .8.4.8.806 0 .404-.4.804-.8.804zM24 10.31C24 4.62 18.62 0 12 0S0 4.62 0 10.31c0 5.099 4.265 9.371 10.025 10.181.39.085.92.255 1.055.585.119.299.078.768.038 1.069l-.17 1.024c-.054.299-.244 1.169 1.024.639 1.27-.53 6.819-4.014 9.31-6.879C22.98 14.93 24 12.751 24 10.31z" />
            </svg>
            加 LINE 並確認出貨
          </a>

          <p className="mt-3 text-[11px] text-red-600 font-bold flex items-start gap-1">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden className="shrink-0 mt-0.5">
              <path d="M12 2L1 21h22L12 2zm0 4l8.5 14.5h-17L12 6zm-1 5v5h2v-5h-2zm0 6v2h2v-2h-2z" />
            </svg>
            <span>48 小時內未完成確認，訂單將自動取消</span>
          </p>
        </>
      ) : (
        <div className="bg-[#fdf7ef] rounded-2xl p-4 space-y-2">
          <p className="text-sm font-black text-[#9F6B3E]">已加入 LINE！請至 LINE 完成最後一步</p>
          <p className="text-xs text-slate-600 leading-relaxed">
            我們已透過 LINE 推送「確認出貨」訊息給您。請打開 LINE 找到「婕樂纖仙女館」對話，點訊息上的
            <span className="font-bold text-[#9F6B3E]">「✅ 確認出貨」</span>按鈕完成下單。
          </p>
          <p className="text-[11px] text-slate-400">確認後此頁面會自動更新狀態（每 6 秒重新查詢）</p>
        </div>
      )}
    </div>
  );
}
