'use client';

/**
 * Serendipity — non-blocking mascot bubble from API `_serendipity` response.
 *
 *   const { show } = useSerendipity();
 *   if (data._serendipity) show(data._serendipity);
 */

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import Mascot from './Mascot';

export interface SerendipityPayload {
  message: string;
  emoji: string;
}

interface SerendipityCtx {
  show: (payload: SerendipityPayload | null | undefined) => void;
}

const Ctx = createContext<SerendipityCtx>({ show: () => {} });
export const useSerendipity = () => useContext(Ctx);

const DISPLAY_MS = 4500;

export function SerendipityProvider({ children }: { children: ReactNode }) {
  const [active, setActive] = useState<SerendipityPayload | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const show = useCallback((payload: SerendipityPayload | null | undefined) => {
    if (!payload?.message) return;
    setActive(payload);
    if (timerRef.current) clearTimeout(timerRef.current);
    timerRef.current = setTimeout(() => setActive(null), DISPLAY_MS);
  }, []);

  useEffect(() => () => {
    if (timerRef.current) clearTimeout(timerRef.current);
  }, []);

  return (
    <Ctx.Provider value={{ show }}>
      {children}
      {active && (
        <div
          className="fixed left-4 right-4 z-[250] mascot-serendipity pointer-events-auto"
          style={{ bottom: 'calc(5rem + env(safe-area-inset-bottom))' }}
          onClick={() => setActive(null)}
        >
          <div className="max-w-md mx-auto flex items-end gap-3">
            <Mascot stage="bloom" mood="excited" size={56} />
            <div className="flex-1 bg-white rounded-2xl px-4 py-3 shadow-2xl border border-[#e7d9cb] relative">
              <span className="absolute -left-2 bottom-5 w-3 h-3 bg-white border-l border-b border-[#e7d9cb] rotate-45" />
              <div className="text-lg leading-none mb-1">{active.emoji}</div>
              <div className="text-[13px] font-black text-slate-700 leading-snug">{active.message}</div>
            </div>
          </div>
        </div>
      )}
      <style jsx global>{`
        @keyframes serendipityIn {
          0% { opacity: 0; transform: translateY(20px); }
          60% { opacity: 1; transform: translateY(-4px); }
          100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes serendipityOut {
          to { opacity: 0; transform: translateY(10px); }
        }
        .mascot-serendipity {
          animation: serendipityIn 0.4s cubic-bezier(0.2, 0.8, 0.2, 1) forwards,
                     serendipityOut 0.3s ease-in forwards ${DISPLAY_MS - 300}ms;
        }
        @media (prefers-reduced-motion: reduce) {
          .mascot-serendipity { animation: none; }
        }
      `}</style>
    </Ctx.Provider>
  );
}
