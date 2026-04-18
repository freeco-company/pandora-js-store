'use client';

import { createContext, useContext, useState, useEffect, useCallback, useMemo, type ReactNode } from 'react';
import { API_URL, type Customer } from '@/lib/api';

interface AuthState {
  customer: Customer | null;
  token: string | null;
  loading: boolean;
  isLoggedIn: boolean;
  login: () => void;
  loginWithLine: () => void;
  logout: () => void;
  setAuth: (token: string, customer: Customer) => void;
}

const AuthContext = createContext<AuthState>({
  customer: null,
  token: null,
  loading: true,
  isLoggedIn: false,
  login: () => {},
  loginWithLine: () => {},
  logout: () => {},
  setAuth: () => {},
});

export function useAuth() {
  return useContext(AuthContext);
}

const TOKEN_KEY = 'pandora-auth-token';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [customer, setCustomer] = useState<Customer | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  const verify = useCallback(async () => {
    const stored = localStorage.getItem(TOKEN_KEY);
    if (!stored) {
      setToken(null);
      setCustomer(null);
      setLoading(false);
      return;
    }

    setToken(stored);

    try {
      const res = await fetch(`${API_URL}/auth/me`, {
        headers: {
          Authorization: `Bearer ${stored}`,
          Accept: 'application/json',
        },
      });
      if (res.status === 401 || res.status === 403) {
        localStorage.removeItem(TOKEN_KEY);
        setToken(null);
        setCustomer(null);
      } else if (res.ok) {
        const data: Customer = await res.json();
        setCustomer(data);
      }
    } catch {
      // network error — keep stale token, don't log out
    } finally {
      setLoading(false);
    }
  }, []);

  // Verify on mount + when page restored from bfcache (cancel-OAuth-and-back loop)
  useEffect(() => {
    verify();
    const onShow = (e: PageTransitionEvent) => {
      if (e.persisted) {
        setLoading(true);
        verify();
      }
    };
    window.addEventListener('pageshow', onShow);
    return () => window.removeEventListener('pageshow', onShow);
  }, [verify]);

  const login = useCallback(() => {
    if (typeof window !== 'undefined') {
      sessionStorage.setItem('pandora-login-redirect', window.location.href);
      // Direct navigation — backend returns 302 to Google OAuth
      window.location.href = `${API_URL}/auth/google`;
    }
  }, []);

  const loginWithLine = useCallback(() => {
    if (typeof window !== 'undefined') {
      sessionStorage.setItem('pandora-login-redirect', window.location.href);
      // Direct navigation — backend returns 302 to LINE OAuth
      window.location.href = `${API_URL}/auth/line`;
    }
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem(TOKEN_KEY);
    setToken(null);
    setCustomer(null);
  }, []);

  const setAuth = useCallback((newToken: string, newCustomer: Customer) => {
    localStorage.setItem(TOKEN_KEY, newToken);
    setToken(newToken);
    setCustomer(newCustomer);
  }, []);

  const value = useMemo<AuthState>(() => ({
    customer,
    token,
    loading,
    isLoggedIn: !!customer,
    login,
    loginWithLine,
    logout,
    setAuth,
  }), [customer, token, loading, login, loginWithLine, logout, setAuth]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
