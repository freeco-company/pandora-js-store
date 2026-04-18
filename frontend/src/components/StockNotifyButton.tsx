'use client';

/**
 * Inline "到貨通知" button shown on out-of-stock products.
 * - Logged-in users: one-click subscribe (uses their email from AuthProvider).
 * - Guests: tiny inline email form.
 */

import { useState } from 'react';
import { useAuth } from './AuthProvider';
import { useToast } from './Toast';
import SiteIcon from '@/components/SiteIcon';
import { API_URL } from '@/lib/api';

export default function StockNotifyButton({ slug }: { slug: string }) {
  const { token, isLoggedIn } = useAuth();
  const { toast } = useToast();
  const [state, setState] = useState<'idle' | 'form' | 'submitting' | 'done'>('idle');
  const [email, setEmail] = useState('');

  const submit = async (addr: string) => {
    setState('submitting');
    try {
      const res = await fetch(`${API_URL}/products/${encodeURIComponent(slug)}/notify-stock`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify({ email: addr }),
      });
      if (!res.ok) throw new Error();
      setState('done');
      toast('✓ 到貨會通知你');
    } catch {
      setState('idle');
      toast('訂閱失敗，請稍後再試');
    }
  };

  if (state === 'done') {
    return (
      <div className="w-full py-3 px-6 rounded-full bg-[#fdf7ef] border border-[#e7d9cb] text-center text-sm font-black text-[#9F6B3E]">
        ✓ 到貨後會寄信通知您
      </div>
    );
  }

  if (isLoggedIn) {
    return (
      <button
        type="button"
        onClick={() => submit('')}              // server uses auth'd user's email
        disabled={state === 'submitting'}
        className="w-full py-3 px-6 font-semibold rounded-full bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] hover:bg-[#fdf7ef] transition-colors disabled:opacity-50"
      >
        {state === 'submitting' ? '處理中…' : <><SiteIcon name="bell" size={16} className="inline" /> 到貨通知我</>}
      </button>
    );
  }

  if (state === 'form' || state === 'submitting') {
    const pending = state === 'submitting';
    return (
      <form
        onSubmit={(e) => { e.preventDefault(); if (email) submit(email); }}
        className="flex gap-2"
      >
        <input
          type="email" required
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="輸入 Email"
          disabled={pending}
          className="flex-1 px-4 py-2.5 border border-[#e7d9cb] rounded-full focus:border-[#9F6B3E] outline-none text-sm"
        />
        <button
          type="submit"
          disabled={pending}
          className="px-5 py-2.5 rounded-full bg-[#9F6B3E] text-white font-black text-sm hover:bg-[#85572F] disabled:opacity-50"
        >
          {pending ? '處理中…' : '訂閱'}
        </button>
      </form>
    );
  }

  return (
    <button
      type="button"
      onClick={() => setState('form')}
      className="w-full py-3 px-6 font-semibold rounded-full bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] hover:bg-[#fdf7ef] transition-colors"
    >
      <SiteIcon name="bell" size={16} className="inline" /> 到貨通知我
    </button>
  );
}
