'use client';

import { useState, useEffect, useCallback } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { type Popup, imageUrl } from '@/lib/api';
import { sanitizeHtml } from '@/lib/sanitize';

const STORAGE_PREFIX = 'pandora-popup';

function shouldShow(popup: Popup): boolean {
  const key = `${STORAGE_PREFIX}-${popup.id}`;

  if (popup.display_frequency === 'every_visit') {
    // Check session storage instead of localStorage
    if (typeof window !== 'undefined' && sessionStorage.getItem(key)) return false;
    return true;
  }

  if (popup.display_frequency === 'once') {
    if (typeof window !== 'undefined' && localStorage.getItem(key)) return false;
    return true;
  }

  if (popup.display_frequency === 'once_per_day') {
    if (typeof window === 'undefined') return true;
    const lastShown = localStorage.getItem(key);
    if (!lastShown) return true;
    const today = new Date().toDateString();
    return lastShown !== today;
  }

  return true;
}

function markShown(popup: Popup): void {
  const key = `${STORAGE_PREFIX}-${popup.id}`;

  if (popup.display_frequency === 'every_visit') {
    sessionStorage.setItem(key, '1');
  } else if (popup.display_frequency === 'once') {
    localStorage.setItem(key, '1');
  } else if (popup.display_frequency === 'once_per_day') {
    localStorage.setItem(key, new Date().toDateString());
  }
}

export default function HomePopupModal({ popups }: { popups: Popup[] }) {
  const [activePopup, setActivePopup] = useState<Popup | null>(null);
  const [exiting, setExiting] = useState(false);

  useEffect(() => {
    // Find first popup that should be shown
    const timer = setTimeout(() => {
      const popup = popups.find(shouldShow);
      if (popup) {
        setActivePopup(popup);
        markShown(popup);
      }
    }, 1000); // Delay popup appearance

    return () => clearTimeout(timer);
  }, [popups]);

  const close = useCallback(() => {
    setExiting(true);
    setTimeout(() => {
      setActivePopup(null);
      setExiting(false);
    }, 200);
  }, []);

  if (!activePopup) return null;

  const img = imageUrl(activePopup.image);

  const content = (
    <div className={`relative bg-white rounded-xl overflow-hidden shadow-2xl max-w-[480px] w-[90vw] ${exiting ? 'modal-content-exit' : 'modal-content-enter'}`}>
      {/* Close button */}
      <button
        onClick={close}
        className="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-black/40 backdrop-blur-sm flex items-center justify-center text-white hover:bg-black/60 transition-colors"
        aria-label="關閉"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={2} stroke="currentColor" className="w-4 h-4">
          <path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      {img && (
        <div className="relative w-full aspect-[4/5]">
          <Image
            src={img}
            alt={activePopup.title}
            fill
            className="object-cover"
            sizes="480px"
          />
        </div>
      )}

      {!img && activePopup.content && (
        <div className="p-6">
          <h3 className="text-lg font-bold text-gray-900 mb-3">{activePopup.title}</h3>
          <div
            className="prose-article text-sm text-gray-600"
            dangerouslySetInnerHTML={{ __html: sanitizeHtml(activePopup.content) }}
          />
        </div>
      )}
    </div>
  );

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm modal-backdrop-enter"
      style={{ opacity: exiting ? 0 : 1, transition: 'opacity 0.3s ease' }}
      onClick={close}
      role="dialog"
      aria-modal="true"
    >
      <div onClick={(e) => e.stopPropagation()}>
        {activePopup.link ? (
          <Link href={activePopup.link} onClick={close}>
            {content}
          </Link>
        ) : (
          content
        )}
      </div>
    </div>
  );
}
