import type { Metadata } from 'next';
import Link from 'next/link';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: 'FP 團隊｜婕樂纖仙女館',
  description:
    '認識婕樂纖仙女館的專業團隊：創辦人朵朵、認證營養師、客服顧問 — 每一位仙女都有真實故事與專業背景。',
  alternates: { canonical: '/team' },
};

/**
 * Team / author profiles — feeds Google E-E-A-T signals:
 *   Expertise (certifications), Authoritativeness (titles), Trust (photos+story).
 * Edit this file to add real people; each entry emits a `Person` JSON-LD.
 */
interface Member {
  slug: string;
  name: string;
  role: string;
  credentials: string[];
  bio: string;
  specialties: string[];
  emoji: string;
}

const TEAM: Member[] = [
  {
    slug: 'duoduo',
    name: '朵朵',
    role: 'Co-Founder · 婕樂纖仙女館',
    credentials: ['健康食品業 8 年資歷', '台灣電商創業家'],
    bio: '從自己體驗過婕樂纖開始，因為真的看到身邊的人改變，決定創辦仙女館 FP，用「從仙女變成 Pandora」的精神，帶領團隊把好東西分享給更多女性。',
    specialties: ['女性保健', '品牌經營', '顧客服務'],
    emoji: '🌸',
  },
  {
    slug: 'consultant-nutrition',
    name: '營養師顧問團',
    role: '營養師陪伴班',
    credentials: ['中華民國專技高考合格營養師', '食品科學碩士'],
    bio: '由合格營養師組成的陪伴團隊。纖體系列單筆滿 $6,600 即可加入營養師陪伴班，享有專屬飲食指導與持續追蹤。完成訂單後私訊截圖即可啟動。',
    specialties: ['體重管理', '飲食指導', '營養衛教'],
    emoji: '🥗',
  },
  {
    slug: 'customer-support',
    name: '客服仙女',
    role: '1 對 1 LINE 諮詢',
    credentials: ['週一至週五 10:00-18:00 上線'],
    bio: '每位顧客背後都有專屬客服仙女，從下單到售後、換貨、產品諮詢全包辦。平均 1 小時內回覆訊息。',
    specialties: ['訂單處理', '售後服務', '產品諮詢'],
    emoji: '💬',
  },
];

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora-dev.js-store.com.tw';

function personSchema(m: Member) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Person',
    '@id': `${siteUrl}/team#${m.slug}`,
    name: m.name,
    jobTitle: m.role,
    description: m.bio,
    knowsAbout: m.specialties,
    hasCredential: m.credentials.map((c) => ({
      '@type': 'EducationalOccupationalCredential',
      name: c,
    })),
    worksFor: { '@id': `${siteUrl}/#organization` },
  };
}

export default function TeamPage() {
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: 'FP 團隊' },
  ]);

  return (
    <div className="relative">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: jsonLdScript(breadcrumbs, ...TEAM.map(personSchema)),
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
        <div className="relative max-w-4xl mx-auto px-5 sm:px-8 lg:px-12 py-16 sm:py-20 text-center">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-4 shadow-sm">
              <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
              <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">FP TEAM</span>
            </div>
          </ScrollReveal>
          <TextReveal
            as="h1"
            text="FP 團隊"
            className="text-3xl sm:text-5xl font-bold text-[#9F6B3E] tracking-tight"
            stagger={70}
          />
          <ScrollReveal variant="fade-up" delay={300}>
            <p className="text-sm sm:text-base text-gray-700 mt-4 max-w-xl mx-auto">
              每一個推薦背後，都是一位**真實的仙女**、一份專業背景、一段親身故事
            </p>
          </ScrollReveal>
        </div>
        <svg className="absolute bottom-0 left-0 right-0 w-full h-10" preserveAspectRatio="none" viewBox="0 0 1200 80" aria-hidden>
          <path d="M0 40 C 300 80, 600 0, 900 40 C 1050 60, 1150 50, 1200 40 L 1200 80 L 0 80 Z" fill="#ffffff" />
        </svg>
      </section>

      <main className="max-w-4xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-14 space-y-6">
        {TEAM.map((m) => (
          <article
            key={m.slug}
            id={m.slug}
            className="bg-white rounded-3xl border border-[#e7d9cb] p-6 sm:p-8 hover:shadow-md transition-shadow"
          >
            <div className="flex items-start gap-4 sm:gap-6">
              <div className="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] flex items-center justify-center text-4xl sm:text-5xl shrink-0">
                {m.emoji}
              </div>
              <div className="flex-1 min-w-0">
                <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">{m.role.toUpperCase().split(' · ')[0]}</div>
                <h2 className="text-lg sm:text-2xl font-black text-gray-900 mt-0.5">{m.name}</h2>
                <div className="text-xs sm:text-sm text-gray-600 mt-0.5">{m.role}</div>
              </div>
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
              {m.credentials.map((c) => (
                <span key={c} className="px-2.5 py-1 rounded-full bg-[#fdf7ef] text-[11px] font-black text-[#7a5836]">
                  🎓 {c}
                </span>
              ))}
            </div>

            <p className="mt-4 text-sm text-gray-700 leading-relaxed">{m.bio}</p>

            <div className="mt-4 pt-4 border-t border-[#e7d9cb]">
              <div className="text-[11px] font-black text-gray-500 tracking-wider mb-2">專長</div>
              <div className="flex flex-wrap gap-2">
                {m.specialties.map((s) => (
                  <span key={s} className="px-2 py-0.5 rounded-md bg-slate-100 text-[11px] text-slate-700">
                    {s}
                  </span>
                ))}
              </div>
            </div>
          </article>
        ))}

        {/* 營養師陪伴班 CTA */}
        <section className="mt-8 bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-8 border border-[#e7d9cb] text-center">
          <h3 className="text-lg font-black text-[#9F6B3E]">營養師陪伴班</h3>
          <p className="text-sm text-gray-700 mt-2 mb-5">
            纖體系列單筆滿 $6,600，即可享有營養師專屬飲食指導與持續追蹤陪伴。
            <br />
            完成訂單後私訊截圖即可啟動。
          </p>
          <Link
            href="/products"
            className="inline-flex items-center gap-2 px-6 py-3 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-colors"
          >
            選購纖體商品 →
          </Link>
        </section>
      </main>
    </div>
  );
}
