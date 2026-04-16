import type { Metadata } from 'next';
import Link from 'next/link';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import { breadcrumbSchema, faqSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '常見問題｜婕樂纖仙女館',
  description:
    '婕樂纖仙女館常見問題：三階梯定價怎麼算？VIP 價怎麼觸發？加盟怎麼加入？ECPay 付款與退換貨政策一次看懂。',
  alternates: { canonical: '/faq' },
};

/**
 * Q&A source of truth — also feeds the FAQPage JSON-LD.
 * Keep answers self-contained (no "see above") for AI-overview / Perplexity citations.
 */
const FAQ_GROUPS: Array<{
  title: string;
  items: Array<{ question: string; answer: string }>;
}> = [
  {
    title: '定價與優惠',
    items: [
      {
        question: '什麼是三階梯定價？',
        answer:
          '婕樂纖仙女館提供三種價格階梯：單件購買享「原價」、任選兩件以上商品即自動切換為「1+1 搭配價」、搭配價小計滿 NT$4,000 再升級為「VIP 優惠價」。整車用同一個階梯，不跨品項混算。',
      },
      {
        question: 'VIP 優惠價怎麼觸發？',
        answer:
          '購物車內所有商品的搭配價小計加總達到 NT$4,000 以上，整車自動升級為 VIP 優惠價，不需要輸入任何優惠碼或加入會員。系統會在購物車頁即時顯示目前階梯與升級提示。',
      },
      {
        question: '可以用優惠券或折價碼嗎？',
        answer:
          '目前官方站上不提供優惠碼折抵，三階梯定價已是最優惠價格。想獲得更低價格的消費者可以考慮加入「自用加盟」（NT$6,600 一次性費用，永久享加盟會員價）。',
      },
    ],
  },
  {
    title: '訂購與付款',
    items: [
      {
        question: '有哪些付款方式？',
        answer:
          '支援綠界科技（ECPay）信用卡線上刷卡、ATM 轉帳、以及銀行匯款。結帳流程符合 PCI-DSS 規範，所有付款資訊都直接由 ECPay 加密處理，店家不儲存信用卡資訊。',
      },
      {
        question: '多久會出貨？',
        answer:
          '付款完成後 24 小時內出貨（不含週末與國定假日）。宅配物流約 1-3 個工作天送達，遠地離島可能需要 3-5 天。出貨後會發送追蹤簡訊或 Email。',
      },
      {
        question: '可以統編或要求三聯式發票嗎？',
        answer:
          '可以，結帳時在發票資訊欄填入公司統編與抬頭即可。發票會透過電子發票方式開立並寄送到訂購 Email。',
      },
    ],
  },
  {
    title: '退換貨與客服',
    items: [
      {
        question: '退換貨政策是什麼？',
        answer:
          '商品到貨後鑑賞期 7 天內，保持商品完整未拆封可申請退貨。食品/保健類商品一旦拆封即不接受退貨，但商品瑕疵、錯誤出貨可無條件換貨。詳細規範請見退換貨政策頁面。',
      },
      {
        question: '要怎麼聯絡客服？',
        answer:
          '推薦透過 LINE 官方帳號 @pandorasdo 即時諮詢，或發信至 developer@packageplus-tw.com。週一至週五 10:00-18:00 有專人回覆，LINE 訊息通常 1 小時內回覆。',
      },
    ],
  },
  {
    title: '加入 FP 團隊',
    items: [
      {
        question: '自用加盟與創業加盟差在哪裡？',
        answer:
          '自用加盟 NT$6,600 一次性付費，永久享受加盟會員價，適合每月固定回購、為自己省錢的仙女，沒有業績壓力。創業加盟則提供完整培訓、階梯式獎金、團隊陪伴，適合想將 FP 打造成事業的人。兩者皆不強制分享。',
      },
      {
        question: '創業加盟要如何開始？',
        answer:
          '透過 LINE 或 IG 私訊預約 1 對 1 視訊面談，了解你的期待與時間安排後，會詳細說明階梯等級、獎金結構與培訓內容。完成簽約後會加入新手培訓群組，為期 4 週的入門課程並有督導 1 對 1 陪伴。',
      },
    ],
  },
  {
    title: '商品與健康',
    items: [
      {
        question: 'JEROSSE 婕樂纖的商品是正品嗎？',
        answer:
          '婕樂纖仙女館為 JEROSSE 官方正品授權經銷商，所有商品均為台灣 JEROSSE 婕樂纖原廠正品。每筆訂單皆附原廠包裝，可查詢商品流水號真偽。',
      },
      {
        question: '保健食品有療效嗎？',
        answer:
          '婕樂纖商品為食品，非藥品，不具醫療效能，亦無法取代正規醫療。孕婦、哺乳中婦女、慢性疾病、服用特殊藥物者，請先諮詢醫師或營養師。請依建議量食用，均衡飲食並搭配規律運動，效果因人而異。',
      },
    ],
  },
];

export default function FAQPage() {
  const allQA = FAQ_GROUPS.flatMap((g) => g.items);
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '常見問題' },
  ]);

  return (
    <div className="relative">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: jsonLdScript(faqSchema(allQA), breadcrumbs),
        }}
      />

      {/* Hero */}
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
        <div className="relative max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-14 sm:py-20 text-center">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-4 shadow-sm">
              <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
              <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">
                FAQ · 常見問題
              </span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text="常見問題"
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-lg mx-auto">
              定價、付款、退換貨、加盟 — 最常被問到的問題一次看懂
            </p>
          </ScrollReveal>
        </div>
        <svg
          className="absolute bottom-0 left-0 right-0 w-full h-10"
          preserveAspectRatio="none"
          viewBox="0 0 1200 80"
          aria-hidden
        >
          <path
            d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z"
            fill="#ffffff"
          />
        </svg>
      </section>

      <main className="max-w-3xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-16 space-y-10">
        {FAQ_GROUPS.map((group) => (
          <section key={group.title} className="space-y-3">
            <h2 className="text-lg sm:text-xl font-black text-[#9F6B3E] mb-4 flex items-center gap-2">
              <span className="w-1 h-5 bg-[#9F6B3E] rounded-full" />
              {group.title}
            </h2>
            <div className="divide-y divide-[#e7d9cb] rounded-3xl bg-white border border-[#e7d9cb] overflow-hidden">
              {group.items.map((qa) => (
                <details
                  key={qa.question}
                  className="group px-5 sm:px-6 py-4 hover:bg-[#fdf7ef]/50 transition-colors"
                >
                  <summary className="flex items-start justify-between gap-3 cursor-pointer list-none">
                    <span className="text-sm sm:text-base font-black text-gray-900 flex-1">
                      Q. {qa.question}
                    </span>
                    <svg
                      className="w-5 h-5 text-[#9F6B3E] shrink-0 transition-transform duration-300 group-open:rotate-45"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                      strokeWidth={2.5}
                    >
                      <path strokeLinecap="round" strokeLinejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                  </summary>
                  <p className="mt-3 text-sm text-gray-600 leading-relaxed whitespace-pre-line">
                    {qa.answer}
                  </p>
                </details>
              ))}
            </div>
          </section>
        ))}

        <section className="mt-12 bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-10 border border-[#e7d9cb] text-center">
          <h3 className="text-lg sm:text-xl font-black text-[#9F6B3E] mb-2">
            沒找到你的問題？
          </h3>
          <p className="text-sm text-gray-600 mb-5">
            直接聯絡客服，1 小時內有專人回覆
          </p>
          <div className="flex flex-wrap gap-3 justify-center">
            <a
              href="https://lin.ee/pandorasdo"
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 px-6 py-3 bg-[#06C755] text-white font-black rounded-full shadow-md hover:bg-[#05b04c] transition-colors min-h-[48px]"
            >
              💬 LINE 諮詢
            </a>
            <Link
              href="/join"
              className="inline-flex items-center gap-2 px-6 py-3 bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] font-black rounded-full hover:bg-[#9F6B3E] hover:text-white transition-colors min-h-[48px]"
            >
              🌱 加入 FP
            </Link>
          </div>
        </section>
      </main>
    </div>
  );
}
