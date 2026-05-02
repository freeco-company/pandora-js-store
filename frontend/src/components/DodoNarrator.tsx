'use client';

import Image from 'next/image';

export type DodoMood = 'neutral' | 'happy' | 'cheering';

interface Props {
  line: string;
  mood?: DodoMood;
  size?: number;
  className?: string;
}

/**
 * 朵朵 NPC 旁白 — 集團導師頭像 + 對白氣泡。
 * Voice rules: 妳 / 你 / 朋友 — 不寫「您」「會員」「用戶」。
 * Anchor v1 (2026-04-29 sign off): painterly soft K-anime chibi.
 */
export default function DodoNarrator({
  line,
  mood = 'neutral',
  size = 56,
  className = '',
}: Props) {
  return (
    <div className={`flex items-start gap-3 ${className}`}>
      <div
        className="relative shrink-0 rounded-full overflow-hidden bg-white shadow-sm ring-2 ring-white/70"
        style={{ width: size, height: size }}
        data-mood={mood}
      >
        <Image
          src="/images/mascots/dodo-portrait.png"
          alt="朵朵"
          fill
          sizes={`${size}px`}
          className="object-cover"
        />
      </div>
      <div className="relative flex-1 min-w-0">
        <span
          aria-hidden
          className="absolute left-0 top-3 -translate-x-1 w-2 h-2 rotate-45 bg-white/90 border-l border-b border-[#e7d9cb]"
        />
        <div className="bg-white/90 backdrop-blur rounded-2xl rounded-tl-sm px-4 py-2.5 border border-[#e7d9cb] shadow-sm">
          <div className="text-[10px] font-black tracking-[0.15em] text-[#9F6B3E] mb-0.5">
            朵朵
          </div>
          <p className="text-[13px] leading-snug text-slate-700 font-medium">
            {line}
          </p>
        </div>
      </div>
    </div>
  );
}
