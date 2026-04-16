'use client';

/**
 * Checkout helper: lets a logged-in customer pick from saved addresses
 * instead of typing the full recipient/phone/address triplet.
 *
 * Calls `onSelect(address)` when a card is clicked — parent should hydrate
 * shipping_name / shipping_phone / shipping_address from the address object.
 *
 * Silently renders nothing for guests or when the customer has no saved addresses.
 */

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useAuth } from './AuthProvider';
import { getAddresses, type CustomerAddress } from '@/lib/api';

interface Props {
  onSelect: (addr: CustomerAddress) => void;
  selectedId?: number | null;
}

export default function SavedAddressPicker({ onSelect, selectedId }: Props) {
  const { token, isLoggedIn } = useAuth();
  const [addresses, setAddresses] = useState<CustomerAddress[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!isLoggedIn || !token) return;
    setLoading(true);
    getAddresses(token)
      .then(setAddresses)
      .catch(() => setAddresses([]))
      .finally(() => setLoading(false));
  }, [isLoggedIn, token]);

  // Auto-pick the default address on first load if parent hasn't picked anything yet
  useEffect(() => {
    if (selectedId !== null && selectedId !== undefined) return;
    const def = addresses.find((a) => a.is_default) ?? addresses[0];
    if (def) onSelect(def);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [addresses]);

  if (!isLoggedIn) return null;
  if (loading) return null;
  if (addresses.length === 0) return null;

  return (
    <div className="mb-4">
      <div className="flex items-center justify-between mb-2">
        <span className="text-[11px] font-black text-[#7a5836] tracking-[0.15em]">
          常用地址
        </span>
        <Link
          href="/account/addresses"
          className="text-[11px] font-black text-[#9F6B3E] hover:underline"
        >
          管理 →
        </Link>
      </div>

      <div className="flex gap-2 overflow-x-auto pb-1 scrollbar-hide snap-x snap-mandatory -mx-1 px-1">
        {addresses.map((a) => {
          const active = selectedId === a.id;
          return (
            <button
              key={a.id}
              type="button"
              onClick={() => onSelect(a)}
              className={`shrink-0 snap-start w-64 sm:w-72 p-3 rounded-2xl text-left transition-all ${
                active
                  ? 'bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border-2 border-[#9F6B3E] shadow-sm'
                  : 'bg-white border border-[#e7d9cb] hover:border-[#9F6B3E]'
              }`}
            >
              <div className="flex items-center gap-1.5 mb-1">
                {a.label && (
                  <span className="px-1.5 py-0.5 rounded-full bg-white text-[9px] font-black text-[#9F6B3E]">
                    {a.label}
                  </span>
                )}
                {a.is_default && (
                  <span className="px-1.5 py-0.5 rounded-full bg-[#9F6B3E] text-white text-[9px] font-black">
                    預設
                  </span>
                )}
                {active && (
                  <span className="ml-auto text-[11px] font-black text-[#9F6B3E]">✓ 使用中</span>
                )}
              </div>
              <div className="text-sm font-black text-gray-900 truncate">{a.recipient_name}</div>
              <div className="text-[11px] text-gray-500 truncate">{a.phone}</div>
              <div className="text-[12px] text-gray-700 mt-1 line-clamp-2">
                {a.postal_code ? `${a.postal_code} ` : ''}{a.city}{a.district} {a.street}
              </div>
            </button>
          );
        })}
      </div>
    </div>
  );
}
