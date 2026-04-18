'use client';

import { useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { useAuth } from '@/components/AuthProvider';
import { API_URL } from '@/lib/api';

export default function LineCallbackPage() {
  const searchParams = useSearchParams();
  const { setAuth } = useAuth();
  const [error, setError] = useState(false);

  useEffect(() => {
    const token = searchParams.get('token');

    if (!token) {
      setError(true);
      return;
    }

    fetch(`${API_URL}/auth/me`, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    })
      .then((res) => {
        if (!res.ok) throw new Error('Invalid token');
        return res.json();
      })
      .then((customer) => {
        setAuth(token, customer);

        const redirect = sessionStorage.getItem('pandora-login-redirect');
        sessionStorage.removeItem('pandora-login-redirect');

        setTimeout(() => {
          if (!customer.phone) {
            window.location.href = '/account/complete-profile';
          } else {
            window.location.href = redirect || '/';
          }
        }, 800);
      })
      .catch(() => {
        setError(true);
      });
  }, [searchParams, setAuth]);

  if (error) {
    return (
      <div className="min-h-[60vh] flex flex-col items-center justify-center px-4">
        <div className="text-center">
          <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-8 h-8 text-red-500">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </div>
          <h1 className="text-xl font-bold text-gray-900 mb-2">登入失敗</h1>
          <p className="text-gray-500 mb-6">LINE 驗證失敗，請重新嘗試。</p>
          <a
            href="/account"
            className="inline-flex items-center px-6 py-2.5 bg-[#9F6B3E] text-white font-semibold rounded-full hover:bg-[#85572F] transition-colors"
          >
            返回登入
          </a>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center px-4">
      <div className="text-center">
        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth={1.5} stroke="currentColor" className="w-8 h-8 text-green-500">
            <path strokeLinecap="round" strokeLinejoin="round" d="m4.5 12.75 6 6 9-13.5" />
          </svg>
        </div>
        <h1 className="text-xl font-bold text-gray-900 mb-2">登入成功</h1>
        <p className="text-gray-500">正在為您跳轉...</p>
      </div>
    </div>
  );
}
