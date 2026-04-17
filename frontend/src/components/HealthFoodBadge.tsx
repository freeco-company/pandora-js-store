/**
 * Product badge system — unified chips for ProductCard + full cards for detail.
 *
 * Badge catalog with consistent colors so cards have equal height.
 */

export const BADGE_CATALOG: Record<string, { label: string; color: string; bg: string; border: string }> = {
  health_food:     { label: '健康食品',    color: 'text-[#2e7d32]', bg: 'bg-[#e8f5e9]', border: 'border-[#c8e6c9]' },
  snq:             { label: 'SNQ 品質標章', color: 'text-[#1565c0]', bg: 'bg-[#e3f2fd]', border: 'border-[#bbdefb]' },
  monde_selection: { label: 'Monde Selection', color: 'text-[#e65100]', bg: 'bg-[#fff3e0]', border: 'border-[#ffe0b2]' },
  clean_label:     { label: '潔淨標章 A.A.', color: 'text-[#2e7d32]', bg: 'bg-[#f1f8e9]', border: 'border-[#dcedc8]' },
  patent:          { label: '專利配方',    color: 'text-[#6a1b9a]', bg: 'bg-[#f3e5f5]', border: 'border-[#e1bee7]' },
  official:        { label: '官方授權',    color: 'text-[#9F6B3E]', bg: 'bg-[#fdf7ef]', border: 'border-[#e7d9cb]' },
};

/** Render 1-2 badge chips for a product card. Always renders at least "官方授權". */
export function ProductBadges({ badges, hfCertNo }: { badges?: string[] | null; hfCertNo?: string | null }) {
  const codes: string[] = [];
  if (hfCertNo) codes.push('health_food');
  if (badges) codes.push(...badges.filter((b) => b !== 'health_food'));
  if (codes.length === 0) codes.push('official');

  // Show max 2 chips to keep card height consistent
  return (
    <div className="flex flex-wrap gap-1 min-h-[22px]">
      {codes.slice(0, 2).map((code) => {
        const def = BADGE_CATALOG[code];
        if (!def) return null;
        return (
          <span
            key={code}
            className={`inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] sm:text-[10px] font-black border ${def.color} ${def.bg} ${def.border}`}
          >
            {def.label}
          </span>
        );
      })}
    </div>
  );
}

/** Full health-food cert card for product detail page */
export function HealthFoodBadge({
  certNo,
  claim,
}: {
  certNo?: string | null;
  claim?: string | null;
}) {
  if (!certNo || !claim) return null;

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
