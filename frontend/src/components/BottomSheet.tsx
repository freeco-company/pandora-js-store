'use client';

/**
 * Mobile-first bottom sheet modal. Slides up from the bottom of the viewport.
 * Respects safe-area-inset on notched devices; backdrop dismisses on tap.
 *
 *   <BottomSheet open={open} onClose={close} title="選擇取貨門市">
 *     ...content...
 *   </BottomSheet>
 */

import { useEffect, useRef, type ReactNode } from 'react';

interface Props {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  maxHeight?: string;
}

export default function BottomSheet({
  open,
  onClose,
  title,
  children,
  maxHeight = '85vh',
}: Props) {
  const sheetRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', onEsc);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onEsc);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[200] flex items-end sm:items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/40 bottomsheet-backdrop"
        onClick={onClose}
        aria-hidden
      />

      {/* Sheet */}
      <div
        ref={sheetRef}
        role="dialog"
        aria-modal="true"
        className="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overscroll-contain bottomsheet-content safe-pb"
        style={{ maxHeight }}
      >
        {/* Grab handle */}
        <div className="pt-2 pb-1 flex justify-center sm:hidden">
          <div className="w-10 h-1 rounded-full bg-slate-300" />
        </div>

        {title && (
          <div className="px-5 pt-3 pb-3 border-b border-slate-100 flex items-center justify-between">
            <h2 className="text-base font-black text-slate-800">{title}</h2>
            <button
              onClick={onClose}
              className="touch-target flex items-center justify-center text-slate-400 hover:text-slate-700 -mr-2"
              aria-label="關閉"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        )}

        <div className="overflow-y-auto px-5 py-4" style={{ maxHeight: `calc(${maxHeight} - 4rem)` }}>
          {children}
        </div>
      </div>

      <style jsx>{`
        @keyframes bsBackdropIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes bsSlideIn {
          from { transform: translateY(100%); }
          to { transform: translateY(0); }
        }
        :global(.bottomsheet-backdrop) { animation: bsBackdropIn 0.2s ease-out forwards; }
        :global(.bottomsheet-content) { animation: bsSlideIn 0.3s cubic-bezier(0.2, 0.9, 0.2, 1) forwards; }
        @media (prefers-reduced-motion: reduce) {
          :global(.bottomsheet-backdrop), :global(.bottomsheet-content) { animation: none; }
        }
      `}</style>
    </div>
  );
}
