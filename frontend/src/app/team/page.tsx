import type { Metadata } from 'next';
import Link from 'next/link';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import Mascot from '@/components/Mascot';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '關於 FP｜從仙女到潘朵拉的蛻變之旅',
  description:
    'Fairy Pandora — 不只是品牌名，是每位女性從認識自己開始，打開專屬美麗盒子的旅程。認識 FP 團隊：創辦人朵朵、認證營養師、專屬客服仙女。',
  alternates: { canonical: '/team' },
};

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora-dev.js-store.com.tw';

// JSON-LD for E-E-A-T
const teamJsonLd = [
  {
    '@context': 'https://schema.org',
    '@type': 'Person',
    '@id': `${siteUrl}/team#duoduo`,
    name: '朵朵',
    jobTitle: 'Co-Founder · 婕樂纖仙女館',
    description: '從自己體驗開始，帶領團隊把好東西分享給更多女性',
    worksFor: { '@id': `${siteUrl}/#organization` },
  },
];

export default function TeamPage() {
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '關於 FP' },
  ]);

  return (
    <div className="relative">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbs, ...teamJsonLd) }}
      />

      {/* ═══ HERO — 全幅沈浸式 ═══ */}
      <section className="relative min-h-[70vh] sm:min-h-[80vh] flex items-center overflow-hidden">
        <div
          className="absolute inset-0"
          style={{
            background:
              'radial-gradient(ellipse at 30% 20%, rgba(247,199,154,0.3), transparent 60%),' +
              'radial-gradient(ellipse at 70% 80%, rgba(159,107,62,0.15), transparent 50%),' +
              'linear-gradient(180deg, #e7d9cb 0%, #f7eee3 40%, #ffffff 100%)',
          }}
        />
        <FloatingShapes />
        <div className="relative w-full max-w-5xl mx-auto px-5 sm:px-8 lg:px-12 py-16 sm:py-24">
          <div className="flex flex-col lg:flex-row items-center gap-10 lg:gap-16">
            {/* Left: mascot journey visual */}
            <div className="flex items-end gap-3 sm:gap-4 shrink-0">
              <ScrollReveal variant="fade-up" delay={0}>
                <div className="text-center opacity-50">
                  <Mascot stage="seedling" mood="neutral" size={56} />
                  <div className="text-[9px] font-bold text-gray-400 mt-1">萌芽</div>
                </div>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={150}>
                <div className="text-center opacity-70">
                  <Mascot stage="sprout" mood="happy" size={80} />
                  <div className="text-[10px] font-bold text-[#9F6B3E] mt-1">成長</div>
                </div>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={300}>
                <div className="text-center">
                  <Mascot stage="bloom" mood="excited" size={110} />
                  <div className="text-[11px] font-black text-[#9F6B3E] mt-1">綻放</div>
                </div>
              </ScrollReveal>
            </div>

            {/* Right: headline */}
            <div className="text-center lg:text-left flex-1">
              <ScrollReveal variant="fade-up">
                <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/70 backdrop-blur rounded-full border border-white/80 shadow-sm mb-5">
                  <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
                  <span className="text-[10px] font-black text-[#9F6B3E] tracking-[0.25em]">FAIRY PANDORA</span>
                </div>
              </ScrollReveal>
              <TextReveal
                as="h1"
                text="從仙女，到潘朵拉"
                className="text-3xl sm:text-4xl lg:text-5xl font-black text-[#3d2e22] tracking-tight leading-tight"
                stagger={80}
              />
              <ScrollReveal variant="fade-up" delay={400}>
                <p className="text-base sm:text-lg text-gray-600 mt-5 max-w-lg leading-relaxed">
                  每位女性心裡都住著一位仙女。
                  <br className="hidden sm:block" />
                  FP 的使命，是陪你打開那個專屬的盒子 —
                  <br className="hidden sm:block" />
                  <strong className="text-[#9F6B3E]">從認識自己，到綻放成最好的自己。</strong>
                </p>
              </ScrollReveal>
              <ScrollReveal variant="fade-up" delay={600}>
                <div className="mt-8 flex flex-col sm:flex-row gap-3 justify-center lg:justify-start">
                  <Link
                    href="/products"
                    className="px-7 py-3.5 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-all shadow-lg shadow-[#9F6B3E]/20 text-center min-h-[48px] flex items-center justify-center"
                  >
                    開始我的蛻變旅程 →
                  </Link>
                </div>
              </ScrollReveal>
            </div>
          </div>
        </div>
      </section>

      {/* ═══ BRAND STORY — 為什麼是 FP ═══ */}
      <section className="bg-white py-16 sm:py-24">
        <div className="max-w-4xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">OUR STORY</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">為什麼叫 Fairy Pandora？</h2>
            </div>
          </ScrollReveal>

          <div className="grid md:grid-cols-2 gap-8 items-center">
            <ScrollReveal variant="fade-up" delay={100}>
              <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-8 border border-[#e7d9cb]">
                <div className="text-5xl mb-4">🧚‍♀️</div>
                <h3 className="text-lg font-black text-[#9F6B3E]">Fairy · 仙女</h3>
                <p className="text-sm text-gray-600 mt-3 leading-relaxed">
                  每個女生都是仙女，只是有時候忘了。婕樂纖是我們找回自信的起點 — 不是變成別人，而是成為更好的自己。
                </p>
              </div>
            </ScrollReveal>
            <ScrollReveal variant="fade-up" delay={250}>
              <div className="bg-gradient-to-br from-[#f7eee3] to-[#e7d9cb] rounded-3xl p-8 border border-[#d4c4b0]">
                <div className="text-5xl mb-4">📦</div>
                <h3 className="text-lg font-black text-[#85572F]">Pandora · 潘朵拉</h3>
                <p className="text-sm text-gray-600 mt-3 leading-relaxed">
                  潘朵拉的盒子不是災難，是希望。打開它需要勇氣 — 而我們在這裡，陪你一起打開。裡面裝的是屬於你的健康、美麗、和自信。
                </p>
              </div>
            </ScrollReveal>
          </div>
        </div>
      </section>

      {/* ═══ JOURNEY — 四步蛻變 ═══ */}
      <section
        className="py-16 sm:py-24 overflow-hidden"
        style={{ background: 'linear-gradient(180deg, #ffffff 0%, #fdf7ef 50%, #ffffff 100%)' }}
      >
        <div className="max-w-5xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-14">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">YOUR JOURNEY</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">你的蛻變旅程</h2>
            </div>
          </ScrollReveal>

          <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {[
              { step: '01', emoji: '🌱', title: '遇見', desc: '第一次認識婕樂纖，對健康有了新的想像', color: 'from-[#e8f5e9] to-[#c8e6c9]' },
              { step: '02', emoji: '🌿', title: '探索', desc: '了解三階梯定價，找到最適合自己的組合搭配', color: 'from-[#fdf7ef] to-[#f7eee3]' },
              { step: '03', emoji: '🌸', title: '蛻變', desc: '營養師陪伴，堅持讓改變發生，看見自己的不同', color: 'from-[#fce4ec] to-[#f8bbd0]' },
              { step: '04', emoji: '✨', title: '綻放', desc: '成為自信的潘朵拉，把好東西分享給身邊的人', color: 'from-[#fff8e1] to-[#ffecb3]' },
            ].map((s, i) => (
              <ScrollReveal key={s.step} variant="fade-up" delay={i * 120}>
                <div className={`bg-gradient-to-br ${s.color} rounded-3xl p-6 h-full border border-white/50 hover:shadow-lg hover:-translate-y-1 transition-all`}>
                  <div className="text-[10px] font-black tracking-[0.2em] text-gray-400 mb-3">STEP {s.step}</div>
                  <div className="text-4xl mb-3">{s.emoji}</div>
                  <h3 className="text-lg font-black text-[#3d2e22]">{s.title}</h3>
                  <p className="text-sm text-gray-600 mt-2 leading-relaxed">{s.desc}</p>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ TEAM — 誰在陪你 ═══ */}
      <section className="bg-white py-16 sm:py-24">
        <div className="max-w-5xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">WHO WE ARE</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">陪你蛻變的人</h2>
            </div>
          </ScrollReveal>

          <div className="grid md:grid-cols-3 gap-6">
            {/* 朵朵 */}
            <ScrollReveal variant="fade-up" delay={0}>
              <div className="bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] rounded-3xl p-6 sm:p-8 border border-[#e7d9cb] text-center h-full">
                <div className="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-[#e7d9cb] to-[#9F6B3E] flex items-center justify-center text-4xl shadow-lg mb-4">
                  🌸
                </div>
                <div className="text-[10px] font-black tracking-[0.2em] text-[#9F6B3E] mb-1">CO-FOUNDER</div>
                <h3 className="text-xl font-black text-[#3d2e22]">朵朵</h3>
                <div className="flex flex-wrap justify-center gap-1.5 mt-3">
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#7a5836]">健康食品業 8 年</span>
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#7a5836]">台灣電商創業家</span>
                </div>
                <p className="text-sm text-gray-600 mt-4 leading-relaxed">
                  從自己體驗過婕樂纖開始，因為真的看到身邊的人改變，決定創辦仙女館 FP。
                  用「從仙女變成 Pandora」的精神，帶領團隊把好東西帶給更多女性。
                </p>
              </div>
            </ScrollReveal>

            {/* 營養師 */}
            <ScrollReveal variant="fade-up" delay={150}>
              <div className="bg-gradient-to-br from-[#e8f5e9] to-[#f1f8e9] rounded-3xl p-6 sm:p-8 border border-[#c8e6c9] text-center h-full">
                <div className="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-[#a5d6a7] to-[#4caf50] flex items-center justify-center text-4xl shadow-lg mb-4">
                  🥗
                </div>
                <div className="text-[10px] font-black tracking-[0.2em] text-[#2e7d32] mb-1">NUTRITION TEAM</div>
                <h3 className="text-xl font-black text-[#3d2e22]">營養師顧問團</h3>
                <div className="flex flex-wrap justify-center gap-1.5 mt-3">
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#2e7d32]">專技高考合格營養師</span>
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#2e7d32]">食品科學碩士</span>
                </div>
                <p className="text-sm text-gray-600 mt-4 leading-relaxed">
                  由合格營養師組成的陪伴團隊。纖體系列滿額即可加入陪伴班或陪跑班，享有專屬飲食指導與持續追蹤。
                </p>
              </div>
            </ScrollReveal>

            {/* 客服 */}
            <ScrollReveal variant="fade-up" delay={300}>
              <div className="bg-gradient-to-br from-[#e3f2fd] to-[#bbdefb] rounded-3xl p-6 sm:p-8 border border-[#90caf9] text-center h-full">
                <div className="w-20 h-20 mx-auto rounded-full bg-gradient-to-br from-[#90caf9] to-[#42a5f5] flex items-center justify-center text-4xl shadow-lg mb-4">
                  💬
                </div>
                <div className="text-[10px] font-black tracking-[0.2em] text-[#1565c0] mb-1">CUSTOMER CARE</div>
                <h3 className="text-xl font-black text-[#3d2e22]">客服仙女</h3>
                <div className="flex flex-wrap justify-center gap-1.5 mt-3">
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#1565c0]">1 對 1 LINE 諮詢</span>
                  <span className="px-2 py-0.5 rounded-full bg-white text-[10px] font-bold text-[#1565c0]">週一至週五上線</span>
                </div>
                <p className="text-sm text-gray-600 mt-4 leading-relaxed">
                  每位顧客背後都有專屬客服仙女，從下單到售後、產品諮詢全包辦。平均 1 小時內回覆訊息。
                </p>
              </div>
            </ScrollReveal>
          </div>
        </div>
      </section>

      {/* ═══ VALUES — 我們相信 ═══ */}
      <section
        className="py-16 sm:py-24"
        style={{ background: 'linear-gradient(180deg, #ffffff 0%, #fdf7ef 100%)' }}
      >
        <div className="max-w-4xl mx-auto px-5 sm:px-8">
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-12">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">OUR VALUES</div>
              <h2 className="text-2xl sm:text-3xl font-black text-[#3d2e22]">我們相信</h2>
            </div>
          </ScrollReveal>

          <div className="grid sm:grid-cols-3 gap-5">
            {[
              { icon: '🔒', title: '正品堅持', desc: 'JEROSSE 官方授權經銷，每一件都是原廠出貨。你買到的，跟品牌官網完全一樣。' },
              { icon: '🤝', title: '真心陪伴', desc: '不催促、不話術。你想了解，我就說；你想等等，我就等。陪伴不是壓力。' },
              { icon: '🌟', title: '長期價值', desc: '健康不是一次性消費。三階梯定價讓你買越多越划算，營養師陪伴讓改變可以持續。' },
            ].map((v, i) => (
              <ScrollReveal key={v.title} variant="fade-up" delay={i * 100}>
                <div className="bg-white rounded-2xl p-6 border border-[#e7d9cb] h-full hover:shadow-md transition-shadow">
                  <div className="text-3xl mb-3">{v.icon}</div>
                  <h3 className="text-base font-black text-[#3d2e22]">{v.title}</h3>
                  <p className="text-sm text-gray-600 mt-2 leading-relaxed">{v.desc}</p>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* ═══ CTA — 開始蛻變 ═══ */}
      <section className="bg-gradient-to-br from-[#9F6B3E] via-[#85572F] to-[#6b4424] text-white py-16 sm:py-24 relative overflow-hidden">
        <div className="absolute inset-0 opacity-10">
          <FloatingShapes />
        </div>
        <div className="relative max-w-3xl mx-auto px-5 sm:px-8 text-center">
          <ScrollReveal variant="zoom-in">
            <div className="inline-block mb-6">
              <Mascot stage="bloom" mood="excited" size={120} />
            </div>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={200}>
            <h2 className="text-2xl sm:text-4xl font-black leading-tight">
              準備好打開你的<br />潘朵拉盒子了嗎？
            </h2>
            <p className="text-sm sm:text-base text-white/80 mt-4 max-w-md mx-auto">
              每個蛻變都從一小步開始。無論你是第一次認識婕樂纖，或是想找到更適合自己的組合 — 我們都在這裡。
            </p>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={400}>
            <div className="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
              <Link
                href="/products"
                className="px-8 py-4 bg-white text-[#9F6B3E] font-black rounded-full hover:bg-white/90 transition-all shadow-xl min-h-[52px] flex items-center justify-center"
              >
                🌸 開始選購
              </Link>
              <Link
                href="/articles"
                className="px-8 py-4 bg-white/15 backdrop-blur text-white font-black rounded-full hover:bg-white/25 transition-all border border-white/20 min-h-[52px] flex items-center justify-center"
              >
                閱讀仙女誌 →
              </Link>
            </div>
          </ScrollReveal>
        </div>
      </section>
    </div>
  );
}
