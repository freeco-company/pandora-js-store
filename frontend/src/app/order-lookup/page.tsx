import type { Metadata } from 'next';
import OrderLookupForm from './OrderLookupForm';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import SiteIcon from '@/components/SiteIcon';

export const metadata: Metadata = {
  title: '訂單查詢',
  alternates: { canonical: '/order-lookup' },
};

export default function OrderLookupPage() {
  return (
    <div className="relative min-h-[70vh]">
      {/* Hero band — unified design language */}
      <section
        className="relative overflow-hidden"
        style={{
          background:
            'radial-gradient(ellipse at 20% 30%, #f7c79a22 0%, transparent 50%),' +
            'radial-gradient(ellipse at 80% 70%, #e7a77e22 0%, transparent 50%),' +
            'linear-gradient(135deg, #e7d9cb 0%, #efe2d1 50%, #e7d9cb 100%)',
        }}
      >
        <FloatingShapes />
        <div className="relative max-w-3xl mx-auto px-5 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-4 shadow-sm">
              <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
              <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">
                ORDER · 訂單追蹤
              </span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text="訂單查詢"
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-md mx-auto">
              輸入訂購時的訂單編號與 Email，即可查詢出貨進度與明細。
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>
      </section>

      {/* Form card */}
      <section className="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 -mt-10 pb-16 relative z-10">
        <ScrollReveal variant="fade-up">
          <div className="bg-white rounded-3xl border border-[#e7d9cb] shadow-[0_20px_40px_-16px_rgba(159,107,62,0.15)] p-6 sm:p-8">
            <OrderLookupForm />
          </div>
        </ScrollReveal>

        <ScrollReveal variant="fade-up" delay={200}>
          <div className="mt-6 grid grid-cols-3 gap-3 text-center">
            {[
              { i: 'package', t: '即時追蹤', d: '出貨進度' },
              { i: 'lock', t: '安全加密', d: 'HTTPS' },
              { i: 'chat', t: '1 對 1 客服', d: '真人回覆' },
            ].map((item, i) => (
              <div key={i} className="p-3 rounded-2xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb]">
                <div className="mb-1 text-[#9F6B3E]"><SiteIcon name={item.i} size={28} /></div>
                <div className="text-xs font-black text-gray-800">{item.t}</div>
                <div className="text-[10px] text-gray-500 mt-0.5">{item.d}</div>
              </div>
            ))}
          </div>
        </ScrollReveal>
      </section>
    </div>
  );
}
