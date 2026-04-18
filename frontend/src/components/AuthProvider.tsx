'use client';

import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react';
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

  // Verify token on mount
  useEffect(() => {
    const stored = localStorage.getItem(TOKEN_KEY);
    if (!stored) {
      setLoading(false);
      return;
    }

    setToken(stored);

    fetch(`${API_URL}/auth/me`, {
      headers: {
        Authorization: `Bearer ${stored}`,
        Accept: 'application/json',
      },
    })
      .then(async (res) => {
        if (res.status === 401 || res.status === 403) {
          // Token genuinely revoked or expired — clear it
          localStorage.removeItem(TOKEN_KEY);
          setToken(null);
          setCustomer(null);
          return;
        }
        if (!res.ok) {
          // Network error, server down (e.g. during deploy) — keep token,
          // don't log the user out. They'll re-verify next page load.
          return;
        }
        const data: Customer = await res.json();
        setCustomer(data);
      })
      .catch(() => {
        // Fetch itself failed (offline, DNS, etc.) — keep token intact.
        // User stays "logged in" with stale customer data until next
        // successful verification. Much better UX than force-logout on
        // every deploy or network hiccup.
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

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

  return (
    <AuthContext.Provider
      value={{
        customer,
        token,
        loading,
        isLoggedIn: !!customer,
        login,
        loginWithLine,
        logout,
        setAuth,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
