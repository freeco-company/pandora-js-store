import type { Metadata } from 'next';
import Link from 'next/link';
import { breadcrumbSchema, faqSchema, jsonLdScript } from '@/lib/jsonld';
import { SITE_URL } from '@/lib/site';

export const revalidate = 86400;

const siteUrl = SITE_URL;

export const metadata: Metadata = {
  title: '婕樂纖哪裡買？官方正品授權購買管道｜婕樂纖仙女館',
  description:
    '婕樂纖 JEROSSE 官方正品哪裡買？婕樂纖仙女館為官方授權經銷商，提供全系列商品：纖飄錠、爆纖錠、纖纖飲X、水光錠、益生菌。三階梯優惠定價，買越多省越多。',
  alternates: { canonical: '/where-to-buy' },
  openGraph: {
    title: '婕樂纖哪裡買？官方授權購買管道',
    description: '婕樂纖仙女館是 JEROSSE 官方正品授權經銷商。安心選購，三階梯定價買越多省越多。',
  },
};

export default function WhereToBuyPage() {
  const faqs = faqSchema([
    { question: '婕樂纖哪裡買比較便宜？', answer: '婕樂纖仙女館提供三階梯定價：單件零售價、任選 2 件搭配價、滿 NT$4,000 升級 VIP 最低價。是最划算的官方授權購買管道。' },
    { question: '婕樂纖仙女館賣的是正品嗎？', answer: '是的，婕樂纖仙女館為 JEROSSE 官方正品授權經銷商，所有商品均為原廠正品，提供完整售後保障。' },
    { question: '網路上其他通路賣的婕樂纖是正品嗎？', answer: '建議透過官方授權管道購買。非授權通路可能有仿品或過期品風險，無法享有售後保障。' },
    { question: '婕樂纖可以超商取貨嗎？', answer: '可以！支援全家、7-ELEVEN 超商取貨付款，也支援信用卡、ATM 轉帳等多元付款方式。' },
    { question: '婕樂纖多久會到貨？', answer: '付款完成後 1-2 個工作天出貨。超商取貨約 2-3 天到店，宅配約 1-2 天送達。' },
  ]);

  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '婕樂纖哪裡買' },
  ]);

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(faqs, breadcrumbs) }}
      />

      <div className="max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-16">
        {/* Hero */}
        <div className="text-center mb-12">
          <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836] mb-2">WHERE TO BUY · 購買指南</div>
          <h1 className="text-3xl sm:text-4xl font-black text-gray-900 mb-3">婕樂纖哪裡買？</h1>
          <p className="text-gray-600 max-w-xl mx-auto">
            婕樂纖仙女館是 <strong className="text-[#9F6B3E]">JEROSSE 官方正品授權經銷商</strong>，
            提供全系列商品與最優惠的三階梯定價。
          </p>
        </div>

        {/* 三階梯定價 */}
        <section className="mb-12">
          <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">三階梯定價 · 買越多省越多</h2>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="text-center p-6 rounded-2xl border border-[#e7d9cb] bg-white">
              <div className="text-3xl mb-2">🛍️</div>
              <div className="text-xs font-black text-gray-400 tracking-wider mb-1">階梯 1</div>
              <div className="font-bold text-gray-800">單件零售價</div>
              <p className="text-xs text-gray-500 mt-2">任選一件商品，以原價購買</p>
            </div>
            <div className="text-center p-6 rounded-2xl border-2 border-[#9F6B3E]/40 bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] shadow-sm">
              <div className="text-3xl mb-2">🎀</div>
              <div className="text-xs font-black text-[#9F6B3E] tracking-wider mb-1">階梯 2 · 最多人選</div>
              <div className="font-bold text-[#9F6B3E]">1+1 搭配價</div>
              <p className="text-xs text-gray-600 mt-2">任選 2 件以上，全車享搭配優惠</p>
            </div>
            <div className="text-center p-6 rounded-2xl bg-gradient-to-br from-[#9F6B3E] to-[#6b4424] text-white shadow-md">
              <div className="text-3xl mb-2">👑</div>
              <div className="text-xs font-black text-[#fcd561] tracking-wider mb-1">階梯 3</div>
              <div className="font-bold">VIP 最低價</div>
              <p className="text-xs text-white/70 mt-2">搭配價滿 $4,000 自動升級最低價</p>
            </div>
          </div>
        </section>

        {/* 為什麼選擇仙女館 */}
        <section className="mb-12">
          <h2 className="text-xl font-bold text-gray-900 mb-6 text-center">為什麼選擇婕樂纖仙女館？</h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {[
              { icon: '✅', title: '官方授權正品', desc: 'JEROSSE 官方正品授權，品質保證不怕買到假貨' },
              { icon: '💰', title: '三階梯最優惠', desc: '獨家三階梯定價，買越多每件省越多' },
              { icon: '🚚', title: '快速出貨', desc: '付款後 1-2 個工作天出貨，超商取貨 / 宅配皆可' },
              { icon: '💳', title: '多元付款', desc: '信用卡、ATM、超商代碼、貨到付款全支援' },
              { icon: '🛡️', title: '售後保障', desc: '完整退換貨機制，購物安心有保障' },
              { icon: '🌱', title: '會員福利', desc: '加入會員享專屬優惠、累積回饋與芽芽成就' },
            ].map(({ icon, title, desc }) => (
              <div key={title} className="flex gap-3 p-4 rounded-xl border border-gray-100">
                <span className="text-2xl shrink-0">{icon}</span>
                <div>
                  <div className="text-sm font-bold text-gray-800">{title}</div>
                  <p className="text-xs text-gray-500 mt-0.5">{desc}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* 熱門商品 */}
        <section className="mb-12">
          <h2 className="text-xl font-bold text-gray-900 mb-4 text-center">熱銷商品</h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-center">
            {[
              { name: '纖飄錠', desc: '健康食品認證・不易形成體脂肪', slug: '纖飄錠-郭雪芙代言健康食品認證' },
              { name: '爆纖錠', desc: '世界品質標章・促進新陳代謝', slug: '爆纖錠-小粉-120顆-瓶' },
              { name: '纖纖飲X', desc: '世界品質金獎・代謝小綠', slug: '纖纖飲X-纖纖輕鬆飲X' },
              { name: '益生菌', desc: '15 種菌種・調整體質', slug: '高機能益生菌-30包-盒' },
              { name: '水光錠', desc: '日本專利玻尿酸・口服美容', slug: '婕樂纖水光錠-日本hyabest專利玻尿酸-60顆-盒' },
              { name: '肽可可', desc: '比利時可可・控卡飽足', slug: '輕卡肽纖飲-肽可可-10包-盒' },
            ].map(({ name, desc, slug }) => (
              <Link
                key={slug}
                href={`/products/${slug}`}
                className="p-4 rounded-xl border border-gray-100 hover:border-[#e7d9cb] hover:bg-[#fdf7ef] transition-colors"
              >
                <div className="text-sm font-bold text-[#9F6B3E]">{name}</div>
                <p className="text-[11px] text-gray-500 mt-1">{desc}</p>
              </Link>
            ))}
          </div>
        </section>

        {/* FAQ */}
        <section className="mb-12">
          <h2 className="text-xl font-bold text-gray-900 mb-4 text-center">購買常見問題</h2>
          <div className="space-y-3">
            {[
              { q: '婕樂纖哪裡買比較便宜？', a: '婕樂纖仙女館提供三階梯定價：單件零售價、任選 2 件搭配價、滿 NT$4,000 升級 VIP 最低價。是最划算的官方授權購買管道。' },
              { q: '婕樂纖仙女館賣的是正品嗎？', a: '是的，婕樂纖仙女館為 JEROSSE 官方正品授權經銷商，所有商品均為原廠正品，提供完整售後保障。' },
              { q: '網路上其他通路賣的婕樂纖是正品嗎？', a: '建議透過官方授權管道購買。非授權通路可能有仿品或過期品風險，無法享有售後保障。' },
              { q: '婕樂纖可以超商取貨嗎？', a: '可以！支援全家、7-ELEVEN 超商取貨付款，也支援信用卡、ATM 轉帳等多元付款方式。' },
              { q: '婕樂纖多久會到貨？', a: '付款完成後 1-2 個工作天出貨。超商取貨約 2-3 天到店，宅配約 1-2 天送達。' },
            ].map(({ q, a }) => (
              <details key={q} className="group border border-gray-100 rounded-xl p-4">
                <summary className="text-sm font-medium text-gray-800 cursor-pointer list-none flex justify-between items-center">
                  {q}
                  <span className="text-gray-400 group-open:rotate-180 transition-transform">▾</span>
                </summary>
                <p className="mt-2 text-sm text-gray-600 leading-relaxed">{a}</p>
              </details>
            ))}
          </div>
        </section>

        {/* CTA */}
        <div className="text-center">
          <Link
            href="/products"
            className="inline-block px-8 py-3 bg-[#9F6B3E] text-white font-medium rounded-full hover:bg-[#8a5d35] transition-colors"
          >
            前往選購
          </Link>
          <p className="text-xs text-gray-400 mt-3">JEROSSE 婕樂纖官方正品授權經銷</p>
        </div>
      </div>
    </>
  );
}
