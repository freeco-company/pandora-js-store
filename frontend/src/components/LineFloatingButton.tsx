'use client';

import { usePathname } from 'next/navigation';

const HIDDEN_PATHS = ['/checkout', '/auth/', '/admin'];

export default function LineFloatingButton() {
  const pathname = usePathname();

  if (HIDDEN_PATHS.some((p) => pathname.startsWith(p))) return null;

  return (
    <a
      href="https://lin.ee/62wj7qa"
      target="_blank"
      rel="noopener noreferrer"
      aria-label="LINE 客服諮詢"
      className="fixed bottom-22 right-4 md:bottom-6 md:right-6 z-[60] w-14 h-14 rounded-full bg-[#06C755] shadow-lg shadow-[#06C755]/30 flex items-center justify-center hover:scale-110 hover:shadow-xl active:scale-95 transition-all"
    >
      <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff" aria-hidden>
        <path d="M12 2C6.48 2 2 5.82 2 10.5c0 4.21 3.74 7.74 8.79 8.4.34.07.81.22.93.51.1.26.07.67.03.94l-.15.9c-.05.28-.22 1.1.96.6s6.37-3.75 8.69-6.42C23.23 13.27 22 11.07 22 10.5 22 5.82 17.52 2 12 2z" />
      </svg>
    </a>
  );
}
