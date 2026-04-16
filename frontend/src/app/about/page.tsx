import type { Metadata } from 'next';
import Link from 'next/link';
import ScrollReveal from '@/components/ScrollReveal';

export const metadata: Metadata = {
  title: '關於 FP · Fairy Pandora',
  description:
    'Fairy Pandora（FP）— 婕樂纖團隊，由朵朵創辦。專注 JEROSSE 品牌推廣、團隊賦能、課程培訓。歡迎想加入仙女事業的妳一起成長。',
  alternates: { canonical: '/about' },
};

export default function AboutPage() {
  return (
    <div className="relative bg-[#1a1410] text-[#f7eee3] overflow-hidden">
      {/* Editorial hero — dark moody, magazine cover style */}
      <section className="relative min-h-[80vh] flex items-end overflow-hidden">
        <div
          className="absolute inset-0"
          style={{
            background:
              'radial-gradient(ellipse 80% 50% at 50% 120%, rgba(159,107,62,0.5) 0%, transparent 60%),' +
              'radial-gradient(ellipse 60% 40% at 10% 20%, rgba(224,116,140,0.15) 0%, transparent 70%),' +
              'linear-gradient(180deg, #1a1410 0%, #2b1f16 100%)',
          }}
        />
        {/* Giant FP typographic mark */}
        <div
          className="absolute inset-0 flex items-center justify-center pointer-events-none select-none"
          aria-hidden
        >
          <div
            className="font-black text-[30vw] sm:text-[26vw] leading-none tracking-tighter opacity-10"
            style={{
              color: '#e7d9cb',
              WebkitTextStroke: '1px #9F6B3E',
              fontFamily: 'Inter, "Microsoft JhengHei", sans-serif',
            }}
          >
            FP
          </div>
        </div>

        <div className="relative max-w-6xl mx-auto px-5 sm:px-8 lg:px-12 w-full pb-16 sm:pb-24">
          <ScrollReveal variant="fade-up">
            <div className="inline-flex items-center gap-2 mb-6">
              <span className="w-10 h-px bg-[#e7d9cb]/50" />
              <span className="text-[11px] font-black tracking-[0.4em] text-[#e7d9cb]/70">FAIRY · PANDORA</span>
            </div>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={100}>
            <h1 className="text-[clamp(2.5rem,9vw,6rem)] font-black leading-[1.05] tracking-tight mb-6">
              <span className="block text-[#f7eee3]">仙女的事業，</span>
              <span className="block text-transparent" style={{ WebkitTextStroke: '1.5px #e7d9cb' }}>
                從這裡開始。
              </span>
            </h1>
          </ScrollReveal>
          <ScrollReveal variant="fade-up" delay={250}>
            <p className="max-w-xl text-[#e7d9cb]/80 text-base sm:text-lg leading-relaxed">
              Fairy Pandora — 婕樂纖官方經銷團隊。
              <br />
              一群相信健康、美麗、自主可以並存的仙女，帶著 JEROSSE 走進千萬女性的生活。
            </p>
          </ScrollReveal>
        </div>
      </section>

      {/* Founder — editorial cover */}
      <section className="relative py-24 sm:py-32">
        <div className="max-w-5xl mx-auto px-5 sm:px-8 lg:px-12 grid md:grid-cols-[1fr_1.3fr] gap-10 items-center">
          <ScrollReveal variant="fade-right">
            <div className="relative">
              <div
                className="aspect-[4/5] rounded-[3rem] bg-gradient-to-br from-[#9F6B3E]/40 via-[#c8835a]/30 to-[#e7a77e]/20 border border-[#9F6B3E]/30 flex items-center justify-center text-8xl sm:text-9xl relative overflow-hidden"
              >
                <span className="relative z-10">🌸</span>
                {/* Decorative corner type */}
                <div className="absolute top-4 left-4 text-[10px] font-black tracking-[0.3em] text-[#e7d9cb]/60">
                  FOUNDER · 01
                </div>
                <div className="absolute bottom-4 right-4 text-[10px] font-black tracking-[0.3em] text-[#e7d9cb]/60">
                  FP / TEAM
                </div>
              </div>
              <div className="absolute -top-4 -right-4 px-4 py-2 bg-[#9F6B3E] text-[#f7eee3] text-[11px] font-black tracking-[0.2em] rounded-full shadow-xl">
                CO-FOUNDER
              </div>
            </div>
          </ScrollReveal>

          <ScrollReveal variant="fade-left" delay={100}>
            <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E] mb-3">MEET THE FOUNDER</div>
            <h2 className="text-4xl sm:text-5xl font-black leading-tight mb-5 text-[#f7eee3]">
              朵朵<span className="block text-[#9F6B3E] text-2xl sm:text-3xl font-bold mt-2">Pandora&apos;s Voice</span>
            </h2>
            <div className="h-px bg-gradient-to-r from-[#9F6B3E] via-[#e7d9cb]/50 to-transparent mb-6" />
            <div className="space-y-4 text-[#e7d9cb]/85 leading-relaxed">
              <p>
                從一位單純愛用者，到集結上百位仙女的團隊核心。朵朵相信：
                <strong className="text-[#f7eee3]">「健康美麗不是特權，是每位女性可以擁有的日常。」</strong>
              </p>
              <p>
                Fairy Pandora 就是在這樣的信念中誕生 — 不只是銷售產品，更是幫助每一個加入的妳，擁有自己的事業、自己的節奏、自己的光。
              </p>
            </div>
            <div className="mt-6 flex items-center gap-4 text-[11px] font-black tracking-[0.2em] text-[#e7d9cb]/60">
              <div>
                <div className="text-3xl font-black text-[#9F6B3E]">200+</div>
                <div className="mt-1">TEAM MEMBERS</div>
              </div>
              <div className="w-px h-12 bg-[#e7d9cb]/20" />
              <div>
                <div className="text-3xl font-black text-[#9F6B3E]">5年</div>
                <div className="mt-1">BRAND JOURNEY</div>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Three pillars — horizontal editorial cards */}
      <section className="relative py-24 sm:py-32 border-t border-[#9F6B3E]/20">
        <div className="max-w-6xl mx-auto px-5 sm:px-8 lg:px-12">
          <ScrollReveal variant="fade-up">
            <div className="mb-12 sm:mb-16 flex items-end justify-between flex-wrap gap-4">
              <div>
                <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E] mb-2">OUR PILLARS</div>
                <h2 className="text-3xl sm:text-5xl font-black leading-tight">
                  <span className="block">團隊三大</span>
                  <span className="block text-[#9F6B3E]">核心價值</span>
                </h2>
              </div>
              <div className="text-[#e7d9cb]/50 text-xs font-black tracking-[0.3em]">— 03 PRINCIPLES</div>
            </div>
          </ScrollReveal>

          <div className="space-y-4 sm:space-y-6">
            {[
              {
                num: '01',
                t: '真實使用，真誠推薦',
                en: 'AUTHENTIC · GENUINE',
                d: '每位 FP 仙女都是自用有感，才走上分享這條路。沒有話術，只有真實的使用心得與身體變化。我們相信：只有自己用得好，推薦才有說服力。',
                tags: ['自用優先', '真實見證', '品質優先'],
              },
              {
                num: '02',
                t: '系統陪跑，從零起步',
                en: 'TRAINING · SYSTEM',
                d: '產品知識線上課、門診營養師直播問答、文案素材庫、顧客服務 SOP。加入 FP 不是一個人單打獨鬥，是一整套系統帶你從陌生到熟練。',
                tags: ['線上課程', '營養諮詢', '文案協助', '服務 SOP'],
              },
              {
                num: '03',
                t: '姐妹情誼，彼此成就',
                en: 'SISTERHOOD · GROWTH',
                d: '每月團隊聚會、季度旅遊、年度仙女大會。我們不是競爭關係，是姐妹關係。當一個人走得快，一群人走得遠。',
                tags: ['月聚會', '季旅遊', '年度仙女大會'],
              },
            ].map((p, i) => (
              <ScrollReveal key={i} variant="fade-up" delay={i * 100}>
                <article className="group relative bg-gradient-to-br from-[#2b1f16] to-[#1a1410] border border-[#9F6B3E]/20 rounded-3xl p-6 sm:p-10 hover:border-[#9F6B3E]/60 transition-all hover:-translate-y-0.5">
                  <div className="grid sm:grid-cols-[auto_1fr] gap-6 sm:gap-10">
                    <div
                      className="text-7xl sm:text-8xl font-black leading-none tracking-tighter"
                      style={{
                        color: 'transparent',
                        WebkitTextStroke: '2px #9F6B3E',
                        fontFamily: 'Inter, sans-serif',
                      }}
                    >
                      {p.num}
                    </div>
                    <div>
                      <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E] mb-2">{p.en}</div>
                      <h3 className="text-2xl sm:text-3xl font-black mb-4 text-[#f7eee3] leading-tight">{p.t}</h3>
                      <p className="text-[#e7d9cb]/75 leading-relaxed text-sm sm:text-base mb-5">{p.d}</p>
                      <div className="flex flex-wrap gap-2">
                        {p.tags.map((tag) => (
                          <span
                            key={tag}
                            className="inline-flex items-center px-3 py-1 rounded-full border border-[#9F6B3E]/40 text-[11px] font-black tracking-wide text-[#e7d9cb]/90"
                          >
                            {tag}
                          </span>
                        ))}
                      </div>
                    </div>
                  </div>
                </article>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* Courses / training */}
      <section className="relative py-24 sm:py-32 border-t border-[#9F6B3E]/20">
        <div className="max-w-6xl mx-auto px-5 sm:px-8 lg:px-12">
          <ScrollReveal variant="fade-up">
            <div className="mb-10 sm:mb-14 text-center">
              <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E] mb-2">COURSES & GROWTH</div>
              <h2 className="text-3xl sm:text-5xl font-black leading-tight">
                系統化<span className="text-[#9F6B3E]"> 培訓課程</span>
              </h2>
              <p className="text-[#e7d9cb]/60 mt-3 max-w-xl mx-auto text-sm sm:text-base">
                從入門到精通，我們提供完整的學習路徑，讓每位 FP 仙女都能在自己的時間、自己的節奏，穩步成長。
              </p>
            </div>
          </ScrollReveal>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
            {[
              { icon: '📚', cat: 'FOUNDATIONS', t: '品牌與產品知識', d: 'JEROSSE 產品線、成分解析、使用情境、FAQ 整理', hours: '8 小時' },
              { icon: '🎯', cat: 'MARKETING', t: '社群經營實戰', d: 'IG／Threads 內容策略、個人品牌定位、貼文模板、故事範本', hours: '12 小時' },
              { icon: '💬', cat: 'SERVICE', t: '顧客服務與話術', d: '諮詢回應模板、售後服務流程、常見異議處理', hours: '6 小時' },
              { icon: '📊', cat: 'BUSINESS', t: '團隊經營與數據', d: '訂單管理、獎金結構、業績分析、再行銷技巧', hours: '10 小時' },
              { icon: '🧘', cat: 'MINDSET', t: '仙女心智課', d: '自我定位、情緒管理、時間規劃、長期主義', hours: '不限時' },
              { icon: '🌿', cat: 'HEALTH', t: '營養師直播問答', d: '每月 2 場線上直播，專業營養師即時解惑', hours: '每月 2 場' },
            ].map((c, i) => (
              <ScrollReveal key={i} variant="zoom-in" delay={i * 60}>
                <div className="group h-full bg-[#2b1f16]/60 backdrop-blur border border-[#9F6B3E]/20 rounded-2xl p-5 sm:p-6 hover:border-[#9F6B3E]/60 hover:bg-[#2b1f16] transition-all hover:-translate-y-1">
                  <div className="flex items-start justify-between mb-4">
                    <span className="text-3xl">{c.icon}</span>
                    <span className="text-[9px] font-black tracking-[0.3em] text-[#9F6B3E]/70 pt-2">{c.cat}</span>
                  </div>
                  <h3 className="text-lg font-black text-[#f7eee3] mb-2">{c.t}</h3>
                  <p className="text-xs text-[#e7d9cb]/65 leading-relaxed mb-4">{c.d}</p>
                  <div className="text-[11px] font-black tracking-wide text-[#9F6B3E] border-t border-[#9F6B3E]/20 pt-3">
                    ⏱ {c.hours}
                  </div>
                </div>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* Voices — testimonial strip */}
      <section className="relative py-24 sm:py-32 border-t border-[#9F6B3E]/20">
        <div className="max-w-5xl mx-auto px-5 sm:px-8 lg:px-12">
          <ScrollReveal variant="fade-up">
            <div className="mb-10 sm:mb-14">
              <div className="text-[10px] font-black tracking-[0.4em] text-[#9F6B3E] mb-2">TEAM VOICES</div>
              <h2 className="text-3xl sm:text-5xl font-black leading-tight">
                真實的 <span className="text-[#9F6B3E]">她們，</span>
                <br />
                正在實踐。
              </h2>
            </div>
          </ScrollReveal>

          <div className="space-y-8">
            {[
              {
                name: '芳芳',
                role: '全職媽媽 · 3 年',
                quote: '孩子睡著後的兩小時，就是我的事業時間。FP 給的不只是收入，是讓我不再被「媽媽」這個身分定義。',
              },
              {
                name: 'Mia',
                role: '護理師 · 2 年',
                quote: '從輪班制的疲憊，到可以自己安排時間。朵朵一直強調「先把自己照顧好」，這句話改變了我的工作觀。',
              },
              {
                name: '莉雅',
                role: '斜槓設計師 · 1 年',
                quote: '我喜歡 FP 最大的原因是——這不是在催你衝業績。是在陪你變成更好版本的自己。',
              },
            ].map((v, i) => (
              <ScrollReveal key={i} variant="fade-left" delay={i * 100}>
                <blockquote className="relative pl-8 sm:pl-12 border-l-2 border-[#9F6B3E]/50">
                  <div className="absolute -left-2 top-0 text-4xl text-[#9F6B3E]/40 font-black leading-none">“</div>
                  <p className="text-lg sm:text-xl text-[#f7eee3] leading-relaxed mb-4 font-bold">{v.quote}</p>
                  <div className="text-[#9F6B3E] text-sm font-black tracking-wide">
                    — {v.name}
                    <span className="ml-2 text-[#e7d9cb]/50 font-bold">{v.role}</span>
                  </div>
                </blockquote>
              </ScrollReveal>
            ))}
          </div>
        </div>
      </section>

      {/* Final CTA — join us */}
      <section
        className="relative py-24 sm:py-32 overflow-hidden"
        style={{
          background:
            'linear-gradient(135deg, #9F6B3E 0%, #c8835a 100%)',
        }}
      >
        <div className="absolute inset-0 pointer-events-none">
          <div className="absolute -top-10 -left-10 w-80 h-80 rounded-full bg-white/20 blur-3xl" />
          <div className="absolute bottom-0 right-0 w-96 h-96 rounded-full bg-[#2b1f16]/20 blur-3xl" />
        </div>

        <div className="relative max-w-4xl mx-auto px-5 sm:px-8 lg:px-12 text-center">
          <ScrollReveal variant="fade-up">
            <div className="text-[11px] font-black tracking-[0.4em] text-white/80 mb-4">JOIN FP</div>
            <h2 className="text-4xl sm:text-6xl font-black leading-[1.05] text-white mb-6">
              準備好<br />
              成為仙女了嗎？
            </h2>
            <p className="text-white/90 text-base sm:text-lg mb-10 max-w-xl mx-auto leading-relaxed">
              不需要業務經驗、不需要完美口才。<br />
              只需要願意改變、願意學習、願意被陪伴。
            </p>
          </ScrollReveal>

          <ScrollReveal variant="fade-up" delay={150}>
            <div className="flex flex-wrap gap-4 justify-center">
              <a
                href="https://lin.ee/pandorasdo"
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 px-10 py-4 bg-white text-[#9F6B3E] font-black rounded-full shadow-2xl hover:bg-[#fdf7ef] transition-colors min-h-[56px]"
              >
                💬 加入 LINE 了解更多
              </a>
              <Link
                href="/products"
                className="inline-flex items-center gap-2 px-10 py-4 bg-white/10 backdrop-blur border-2 border-white/50 text-white font-black rounded-full hover:bg-white/20 transition-colors min-h-[56px]"
              >
                先看產品 →
              </Link>
            </div>
            <p className="mt-8 text-white/70 text-xs tracking-wide">
              或追蹤朵朵 Instagram <a href="https://www.instagram.com/pandorasdo/" target="_blank" rel="noopener noreferrer" className="font-black underline">@pandorasdo</a> 看團隊日常
            </p>
          </ScrollReveal>
        </div>
      </section>
    </div>
  );
}
