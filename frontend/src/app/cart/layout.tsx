import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '購物車',
  robots: { index: false, follow: false },
};

export default function CartLayout({ children }: { children: React.ReactNode }) {
  return children;
}
