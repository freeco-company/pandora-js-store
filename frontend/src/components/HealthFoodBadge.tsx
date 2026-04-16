/**
 * 衛福部健康食品認證 badge ("小綠人").
 *
 * Render conditionally when the product has both:
 *   - hf_cert_no   (例：衛部健食字第 A00455 號)
 *   - hf_cert_claim (例：輔助調節血脂)
 *
 * Small variant for product cards, full variant for product detail page.
 */

export function HealthFoodBadge({
  certNo,
  claim,
  variant = 'full',
}: {
  certNo?: string | null;
  claim?: string | null;
  variant?: 'full' | 'chip';
}) {
  if (!certNo || !claim) return null;

  if (variant === 'chip') {
    return (
      <span
        className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#e8f5e9] text-[#2e7d32] text-[10px] font-black border border-[#c8e6c9]"
        title={`${certNo}｜${claim}`}
      >
        <svg viewBox="0 0 24 24" className="w-3 h-3" fill="currentColor" aria-hidden="true">
          <path d="M12 2a4.5 4.5 0 00-4.5 4.5c0 1.1.4 2.1 1.07 2.9A6 6 0 006 15v6h12v-6a6 6 0 00-2.57-5.6A4.46 4.46 0 0016.5 6.5 4.5 4.5 0 0012 2zm0 2a2.5 2.5 0 012.5 2.5A2.5 2.5 0 0112 9a2.5 2.5 0 01-2.5-2.5A2.5 2.5 0 0112 4z" />
        </svg>
        健康食品
      </span>
    );
  }

  return (
    <div className="flex items-start gap-3 p-3.5 rounded-xl bg-gradient-to-br from-[#f1f8e9] to-[#e8f5e9] border border-[#c8e6c9]">
      <div className="shrink-0 w-10 h-10 rounded-full bg-white border border-[#c8e6c9] flex items-center justify-center text-[#2e7d32]">
        <svg viewBox="0 0 24 24" className="w-6 h-6" fill="currentColor" aria-hidden="true">
          <path d="M12 2a4.5 4.5 0 00-4.5 4.5c0 1.1.4 2.1 1.07 2.9A6 6 0 006 15v6h12v-6a6 6 0 00-2.57-5.6A4.46 4.46 0 0016.5 6.5 4.5 4.5 0 0012 2zm0 2a2.5 2.5 0 012.5 2.5A2.5 2.5 0 0112 9a2.5 2.5 0 01-2.5-2.5A2.5 2.5 0 0112 4z" />
        </svg>
      </div>
      <div className="flex-1 min-w-0">
        <div className="text-sm font-black text-[#2e7d32]">衛福部健康食品認證</div>
        <div className="mt-1 text-xs text-gray-700">
          <span className="font-bold text-gray-800">核可功效：</span>
          {claim}
        </div>
        <div className="mt-0.5 text-[11px] text-gray-500">{certNo}</div>
      </div>
    </div>
  );
}
