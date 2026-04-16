'use client';

import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';

interface Ctx {
  open: boolean;
  setOpen: (v: boolean) => void;
  toggle: () => void;
}

const MobileMenuCtx = createContext<Ctx>({ open: false, setOpen: () => {}, toggle: () => {} });

export const useMobileMenu = () => useContext(MobileMenuCtx);

export function MobileMenuProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const toggle = useCallback(() => setOpen((v) => !v), []);

  // Body scroll lock when open
  useEffect(() => {
    if (open) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
    return () => { document.body.style.overflow = ''; };
  }, [open]);

  // Close on route change is handled by links' onClick
  return (
    <MobileMenuCtx.Provider value={{ open, setOpen, toggle }}>
      {children}
    </MobileMenuCtx.Provider>
  );
}
