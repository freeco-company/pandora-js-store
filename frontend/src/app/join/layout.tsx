import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: '加入仙女館｜自用省錢 · 經營創業',
  description: '加入婕樂纖仙女館會員，享三階梯優惠價。自用省更多，經營批發另有專屬方案。',
  alternates: { canonical: '/join' },
  openGraph: {
    title: '加入仙女館｜自用省錢 · 經營創業',
    description: '加入婕樂纖仙女館會員，享三階梯優惠價。',
  },
};

export default function JoinLayout({ children }: { children: React.ReactNode }) {
  return children;
}
