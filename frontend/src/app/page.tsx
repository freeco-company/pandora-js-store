import Link from 'next/link';
import dynamic from 'next/dynamic';
import { getProducts, getProductCategories, getBanners, getPopups, type Product, type ProductCategory, type Banner, type Popup } from '@/lib/api';
import ProductCardGrid from '@/components/ProductCardGrid';
import HeroBanner from '@/components/HeroBanner';
import ScrollReveal from '@/components/ScrollReveal';
import TextReveal from '@/components/TextReveal';
import MagneticButton from '@/components/MagneticButton';
import Counter from '@/components/Counter';
import Parallax from '@/components/Parallax';
import FloatingShapes from '@/components/FloatingShapes';
import LogoLoader from '@/components/LogoLoader';
import HeroOrbit from '@/components/HeroOrbit';
import { organizationSchema, websiteSchema, jsonLdScript } from '@/lib/jsonld';

// Below-the-fold — lazy load to shrink initial JS payload
const MarqueeKeywords = dynamic(() => import('@/components/MarqueeKeywords'));
const HealthBeautyNarrative = dynamic(() => import('@/components/HealthBeautyNarrative'));
const HomePopupModal = dynamic(() => import('@/components/HomePopupModal'));
const CampaignCountdown = dynamic(() => import('@/components/CampaignCountdown'));

export const revalidate = 3600;

export default async function HomePage() {
  let products: Product[] = [];
  let categories: ProductCategory[] = [];
  let banners: Banner[] = [];
  let popups: Popup[] = [];

  try { products = await getProducts(); } catch {}
  try { categories = await getProductCategories(); } catch {}
  try { banners = await getBanners(); } catch {}
  try { popups = await getPopups(); } catch {}

  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: jsonLdScript(organizationSchema(), websiteSchema()),
        }}
      />

      {/* HERO BANNER CAROUSEL (official scraped from jerosse.com.tw) */}
      <HeroBanner banners={banners} products={products} />

      {popups.length > 0 && <HomePopupModal popups={popups} />}

      {/* ------------------------------------------------------------------ */}
      {/* 1. HERO WELCOME — parallax + floating shapes + letter-by-letter    */}
      {/* ------------------------------------------------------------------ */}
      <section className="relative overflow-hidden" style={{ backgroundColor: '#e7d9cb' }}>
        <FloatingShapes />

        {/* Hero: sparkle particles */}
        <div className="absolute inset-0 pointer-events-none hidden md:block" aria-hidden>
          {[
            { l: '8%', t: '15%', s: 3, d: 0 },
            { l: '88%', t: '22%', s: 4, d: 1.2 },
            { l: '15%', t: '80%', s: 2, d: 2.4 },
            { l: '80%', t: '75%', s: 5, d: 3.6 },
            { l: '45%', t: '10%', s: 2, d: 0.6 },
            { l: '70%', t: '50%', s: 3, d: 1.8 },
          ].map((p, i) => (
            <span
              key={i}
              className="absolute rounded-full bg-white hero-twinkle"
              style={{
                left: p.l, top: p.t, width: p.s, height: p.s,
                animationDelay: `${p.d}s`,
              }}
            />
          ))}
        </div>

        {/* Desktop: orbit on right side absolute */}
        <div className="hidden lg:block absolute right-[6%] top-1/2 -translate-y-1/2">
          <HeroOrbit size={420} />
        </div>

        <style>{`
          @keyframes hero-twinkle {
            0%, 100% { opacity: 0; transform: scale(0.5); }
            50% { opacity: 0.9; transform: scale(1.4); }
          }
          .hero-twinkle {
            animation: hero-twinkle 3s ease-in-out infinite;
            box-shadow: 0 0 10px rgba(255,255,255,0.6);
          }
          @media (prefers-reduced-motion: reduce), (hover: none) {
            .hero-twinkle { animation: none; opacity: 0.5; }
          }
        `}</style>

        <div className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-20 sm:py-28 lg:py-36">
          <Parallax strength={0.08}>
            <div className="max-w-3xl">
              <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-white/60 backdrop-blur rounded-full border border-white/80 mb-6">
                <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
                <span className="text-[11px] font-black text-[#9F6B3E] tracking-[0.2em]">
                  OFFICIAL · 官方正品授權
                </span>
              </div>

              <div className="mb-6">
                <TextReveal
                  as="h1"
                  text="婕樂纖仙女館"
                  className="text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight text-[#9F6B3E]"
                  stagger={70}
                />
                <TextReveal
                  as="p"
                  text="由內而外，綻放光彩"
                  className="text-xl sm:text-2xl text-gray-800 mt-3 font-bold"
                  stagger={50}
                  delay={400}
                />
              </div>

              <ScrollReveal variant="fade-up" delay={500}>
                <p className="text-base sm:text-lg text-gray-700 mb-8 leading-relaxed max-w-xl">
                  JEROSSE 婕樂纖 — 保健食品 × 美容保養雙軌品牌。<br />
                  每一份配方都為仙女而生，讓健康與美麗自然並行。
                </p>
              </ScrollReveal>

              <ScrollReveal variant="fade-up" delay={650}>
                <div className="flex flex-wrap gap-4 items-center">
                  <MagneticButton
                    href="/products"
                    className="px-8 py-4 bg-[#9F6B3E] text-white font-black rounded-full shadow-lg shadow-[#9F6B3E]/30 hover:bg-[#85572F] hover:shadow-xl hover:shadow-[#9F6B3E]/40 min-h-[52px]"
                  >
                    🌿 立即選購
                  </MagneticButton>
                  <MagneticButton
                    href="/about"
                    className="px-8 py-4 bg-white/70 backdrop-blur-sm border-2 border-[#9F6B3E]/40 text-[#9F6B3E] font-black rounded-full hover:bg-white hover:border-[#9F6B3E] min-h-[52px]"
                    strength={0.25}
                  >
                    了解品牌 →
                  </MagneticButton>
                </div>
              </ScrollReveal>

              <ScrollReveal variant="fade-up" delay={850}>
                <div className="mt-12 flex items-center gap-6 text-xs sm:text-sm">
                  <div className="flex items-center gap-2">
                    <div className="w-9 h-9 rounded-full bg-white/80 flex items-center justify-center text-lg shadow-sm">🏆</div>
                    <div>
                      <div className="font-black text-gray-800">玉山獎</div>
                      <div className="text-[10px] text-gray-500">雙首獎肯定</div>
                    </div>
                  </div>
                  <div className="w-px h-10 bg-[#9F6B3E]/20" />
                  <div className="flex items-center gap-2">
                    <div className="w-9 h-9 rounded-full bg-white/80 flex items-center justify-center text-lg shadow-sm">🛡️</div>
                    <div>
                      <div className="font-black text-gray-800">官方授權</div>
                      <div className="text-[10px] text-gray-500">防偽標籤</div>
                    </div>
                  </div>
                  <div className="w-px h-10 bg-[#9F6B3E]/20" />
                  <div className="flex items-center gap-2">
                    <div className="w-9 h-9 rounded-full bg-white/80 flex items-center justify-center text-lg shadow-sm">💎</div>
                    <div>
                      <div className="font-black text-gray-800">VIP 價</div>
                      <div className="text-[10px] text-gray-500">滿 $4,000</div>
                    </div>
                  </div>
                </div>
              </ScrollReveal>

              {/* Mobile-only: orbit visual centered below hero copy */}
              <div className="lg:hidden mt-12 flex justify-center">
                <HeroOrbit size={260} />
              </div>
            </div>
          </Parallax>
        </div>
      </section>

      {/* Campaign countdown — only visible when an active campaign exists */}
      <CampaignCountdown />

      {/* 3. Marquee keywords */}
      <MarqueeKeywords />

      {/* 4. Animated counters + 3-tier pricing summary */}
      <section className="relative bg-white py-14 sm:py-20 overflow-hidden">
        <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8">
          <div className="grid grid-cols-3 gap-3 sm:gap-6 mb-12 text-center">
            <ScrollReveal variant="zoom-in" delay={0}>
              <div className="px-3 py-6 sm:py-8 rounded-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb] shadow-sm">
                <div className="text-4xl sm:text-5xl font-black text-[#9F6B3E]">
                  <Counter to={346} duration={1800} /><span className="text-2xl ml-0.5">+</span>
                </div>
                <div className="text-xs sm:text-sm font-black text-gray-600 mt-2 tracking-wide">專業健康文章</div>
              </div>
            </ScrollReveal>
            <ScrollReveal variant="zoom-in" delay={120}>
              <div className="px-3 py-6 sm:py-8 rounded-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb] shadow-sm">
                <div className="text-4xl sm:text-5xl font-black text-[#9F6B3E]">
                  <Counter to={20} duration={1200} /><span className="text-2xl ml-0.5">項</span>
                </div>
                <div className="text-xs sm:text-sm font-black text-gray-600 mt-2 tracking-wide">官方精選商品</div>
              </div>
            </ScrollReveal>
            <ScrollReveal variant="zoom-in" delay={240}>
              <div className="px-3 py-6 sm:py-8 rounded-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border border-[#e7d9cb] shadow-sm">
                <div className="text-4xl sm:text-5xl font-black text-[#9F6B3E]">
                  <Counter to={24} duration={1400} suffix="H" />
                </div>
                <div className="text-xs sm:text-sm font-black text-gray-600 mt-2 tracking-wide">快速出貨</div>
              </div>
            </ScrollReveal>
          </div>

          {/* 3-tier pricing — progressive stairs */}
          <ScrollReveal variant="fade-up">
            <div className="text-center mb-8">
              <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">PRICING · 三階梯定價</div>
              <TextReveal
                as="h2"
                text="越買越划算"
                className="text-2xl sm:text-3xl font-black text-gray-900"
                stagger={70}
              />
              <p className="text-sm text-gray-500 mt-2">一鍵升級，全車同享最優惠</p>
            </div>

            <div className="relative grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5">
              {/* Tier 1: Regular */}
              <div
                className="group relative p-5 sm:p-6 rounded-3xl bg-white border border-[#e7d9cb] overflow-hidden transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_16px_32px_-10px_rgba(159,107,62,0.15)]"
                style={{ boxShadow: '0 8px 18px -8px rgba(34,56,101,0.06)' }}
              >
                <div className="flex items-center justify-between mb-3">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#fdf7ef] text-[10px] font-black tracking-[0.15em] text-[#7a5836]">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#c9b89a]" />
                    階梯 1
                  </span>
                  <span className="text-2xl">🌱</span>
                </div>
                <div className="text-xs font-black text-gray-500 mb-1 tracking-wider">SINGLE · 單件購買</div>
                <div className="text-xl sm:text-2xl font-black text-gray-900">原價</div>
                <div className="mt-3 pt-3 border-t border-dashed border-[#e7d9cb]">
                  <div className="text-[11px] text-gray-500 leading-relaxed">
                    小試身手、單品嘗鮮都適合
                  </div>
                </div>
              </div>

              {/* Tier 2: Combo — 推薦 */}
              <div
                className="group relative p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border-2 border-[#9F6B3E]/40 overflow-hidden transition-all duration-300 hover:-translate-y-1 sm:scale-[1.02] sm:-translate-y-1"
                style={{ boxShadow: '0 16px 32px -10px rgba(159,107,62,0.22)' }}
              >
                <span className="absolute -top-3 -right-3 w-20 h-20 rounded-full bg-[#9F6B3E]/8" />
                <span className="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-[#9F6B3E] text-white text-[10px] font-black tracking-wide shadow-md">
                  ★ 最多人選
                </span>
                <div className="flex items-center justify-between mb-3 relative">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white text-[10px] font-black tracking-[0.15em] text-[#9F6B3E]">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#9F6B3E] animate-pulse" />
                    階梯 2
                  </span>
                </div>
                <div className="text-xs font-black text-[#9F6B3E] mb-1 tracking-wider relative">COMBO · 任選 2 件</div>
                <div className="text-xl sm:text-2xl font-black text-[#9F6B3E] relative">1 + 1 搭配價</div>
                <div className="mt-3 pt-3 border-t border-dashed border-[#9F6B3E]/30 relative">
                  <div className="text-[11px] text-[#7a5836] leading-relaxed">
                    任選 2 件以上 · 全車同享階梯 2 價
                  </div>
                </div>
              </div>

              {/* Tier 3: VIP */}
              <div
                className="group relative p-5 sm:p-6 rounded-3xl bg-gradient-to-br from-[#9F6B3E] via-[#8d5f36] to-[#6b4424] text-white overflow-hidden transition-all duration-300 hover:-translate-y-1"
                style={{ boxShadow: '0 20px 40px -12px rgba(159,107,62,0.4)' }}
              >
                <span className="absolute -top-8 -right-8 w-32 h-32 rounded-full bg-white/10" />
                <span className="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-black/20 to-transparent pointer-events-none" />
                <div className="flex items-center justify-between mb-3 relative">
                  <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 backdrop-blur text-[10px] font-black tracking-[0.15em] text-[#fcd561]">
                    <span className="w-1.5 h-1.5 rounded-full bg-[#fcd561]" />
                    階梯 3
                  </span>
                  <span className="text-2xl">👑</span>
                </div>
                <div className="text-xs font-black text-[#fcd561] mb-1 tracking-wider relative">VIP · 滿 $4,000</div>
                <div className="text-xl sm:text-2xl font-black relative">VIP 優惠價</div>
                <div className="mt-3 pt-3 border-t border-dashed border-white/20 relative">
                  <div className="text-[11px] text-white/80 leading-relaxed">
                    搭配滿 NT$4,000 自動升級 · 全車最低價
                  </div>
                </div>
              </div>
            </div>

            <div className="mt-6 text-center">
              <a
                href="/products"
                className="inline-flex items-center gap-2 px-6 py-2.5 bg-white border border-[#e7d9cb] text-[#9F6B3E] rounded-full font-black text-sm hover:border-[#9F6B3E] hover:shadow-md transition-all"
              >
                開始搭配
                <span aria-hidden>→</span>
              </a>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* 5. HEALTH × BEAUTY DUAL NARRATIVE — scroll-linked split */}
      <HealthBeautyNarrative />

      {/* 6. FEATURED PRODUCTS with title reveal */}
      <section className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-20">
        <ScrollReveal variant="blur-in">
          <div className="text-center mb-12">
            <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">FEATURED</div>
            <TextReveal
              as="h2"
              text="精選商品"
              className="text-3xl sm:text-4xl font-bold text-gray-900"
              stagger={70}
            />
            <p className="text-sm text-gray-500 mt-3">官方正品，多件搭配享優惠</p>
          </div>
        </ScrollReveal>

        {products.length > 0 ? (
          <ScrollReveal variant="fade-up" distance={40}>
            <ProductCardGrid products={products.slice(0, 8)} />
            <div className="text-center mt-10">
              <MagneticButton
                href="/products"
                className="px-8 py-3 bg-white border-2 border-[#9F6B3E] text-[#9F6B3E] font-black rounded-full hover:bg-[#9F6B3E] hover:text-white transition-all min-h-[48px]"
                strength={0.25}
              >
                查看全部商品 →
              </MagneticButton>
            </div>
          </ScrollReveal>
        ) : (
          <div className="flex justify-center py-12"><LogoLoader size={64} /></div>
        )}
      </section>

      {/* 7. Trust badges */}
      <section className="bg-[#faf5ee] border-y border-[#e7d9cb] overflow-hidden">
        <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-14">
          <ScrollReveal variant="fade-up">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
              {[
                { emoji: '💳', t: '多元支付 / 貨到付款', d: '刷卡分期・超商取貨' },
                { emoji: '🚚', t: '全館現貨・快速出貨', d: '24H 內發貨' },
                { emoji: '🛡️', t: '官方授權・正品保證', d: '防偽標籤可驗證' },
                { emoji: '👩‍⚕️', t: '專業營養師諮詢', d: '1 對 1 真人服務' },
              ].map((item, i) => (
                <ScrollReveal key={i} variant="fade-up" delay={i * 100}>
                  <div className="flex flex-col items-center gap-3 p-4 rounded-2xl hover:bg-white/60 transition-colors">
                    <div className="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-3xl">
                      {item.emoji}
                    </div>
                    <div>
                      <p className="font-black text-gray-900 text-sm">{item.t}</p>
                      <p className="text-xs text-gray-500 mt-0.5">{item.d}</p>
                    </div>
                  </div>
                </ScrollReveal>
              ))}
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* 8. Categories */}
      {categories.length > 0 && (
        <section className="bg-white py-20">
          <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8">
            <ScrollReveal variant="fade-up">
              <div className="text-center mb-10 sm:mb-14">
                <div className="text-[10px] font-black tracking-[0.3em] text-[#9F6B3E] mb-2">CATEGORIES</div>
                <TextReveal as="h2" text="商品分類" className="text-3xl sm:text-4xl font-bold text-gray-900" stagger={80} />
                <p className="text-sm text-gray-500 mt-3">依需求挑選，找到最適合你的仙女配方</p>
              </div>
            </ScrollReveal>
            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-5">
              {categories.slice(0, 6).map((cat, i) => {
                // Visual tokens by category slug/name — unified height, distinct color
                const visual: { emoji: string; grad: string; ring: string; accent: string } =
                  cat.slug === 'slimming' || cat.name.includes('體重')
                    ? { emoji: '💪', grad: 'from-[#fef6e4] to-[#fbe4b0]', ring: 'ring-[#e0a43a]/20', accent: 'text-[#b37908]' }
                  : cat.slug === 'health' || cat.name.includes('健康保健')
                    ? { emoji: '🛡️', grad: 'from-[#e6f5ef] to-[#c3e5d3]', ring: 'ring-[#4a9d5f]/20', accent: 'text-[#2f6b3e]' }
                  : cat.slug === 'beauty' || cat.name.includes('美容保養')
                    ? { emoji: '✨', grad: 'from-[#fde8f0] to-[#f7c2d2]', ring: 'ring-[#E0748C]/20', accent: 'text-[#9c3e55]' }
                  : cat.name.includes('健康活力')
                    ? { emoji: '🌿', grad: 'from-[#ecf7d9] to-[#cae89e]', ring: 'ring-[#6b9e3c]/20', accent: 'text-[#4a6e27]' }
                  : cat.name.includes('健康維持')
                    ? { emoji: '🍃', grad: 'from-[#e3f1ee] to-[#b7dad1]', ring: 'ring-[#388a7a]/20', accent: 'text-[#256358]' }
                  : cat.name.includes('美容美體')
                    ? { emoji: '🌸', grad: 'from-[#fde3ec] to-[#f9b3c9]', ring: 'ring-[#d04d7d]/20', accent: 'text-[#8e2350]' }
                    : { emoji: '🌼', grad: 'from-[#fdf7ef] to-[#f7eee3]', ring: 'ring-[#9F6B3E]/20', accent: 'text-[#9F6B3E]' };

                return (
                  <ScrollReveal key={cat.id} variant="zoom-in" delay={i * 70}>
                    <Link
                      href={`/products?category=${cat.slug}`}
                      className={`group relative block rounded-3xl bg-gradient-to-br ${visual.grad} ring-1 ${visual.ring} overflow-hidden hover:shadow-xl hover:-translate-y-1 transition-all duration-400`}
                      data-cursor="card"
                      data-cursor-label="查看"
                    >
                      {/* Decorative corner orb */}
                      <span className="absolute -top-6 -right-6 w-20 h-20 rounded-full bg-white/40 blur-xl" aria-hidden />
                      <span className="absolute -bottom-4 -left-4 w-16 h-16 rounded-full bg-white/30 blur-lg" aria-hidden />

                      {/* Content — fixed height for consistency */}
                      <div className="relative aspect-[5/4] p-5 flex flex-col justify-between">
                        <div className="flex items-start justify-between">
                          <span className="text-4xl sm:text-5xl drop-shadow-sm transition-transform duration-400 group-hover:scale-110 group-hover:rotate-[-6deg]">
                            {visual.emoji}
                          </span>
                          <span className={`inline-flex items-center px-2 py-0.5 rounded-full bg-white/70 backdrop-blur text-[10px] font-black ${visual.accent} shrink-0`}>
                            {cat.products_count ?? 0}
                          </span>
                        </div>

                        <div>
                          <h3 className="text-base sm:text-lg font-black text-gray-900 leading-tight line-clamp-2 min-h-[2.4em]">
                            {cat.name}
                          </h3>
                          <div className={`mt-1.5 text-[11px] font-black ${visual.accent} inline-flex items-center gap-1`}>
                            探索系列
                            <span className="transition-transform duration-300 group-hover:translate-x-0.5">→</span>
                          </div>
                        </div>
                      </div>
                    </Link>
                  </ScrollReveal>
                );
              })}
            </div>
          </div>
        </section>
      )}

      {/* 9. Final CTA — warm not black */}
      <section
        className="relative overflow-hidden py-24"
        style={{ background: 'linear-gradient(135deg, #9F6B3E 0%, #c8835a 50%, #e7a77e 100%)' }}
      >
        <div className="absolute inset-0 opacity-30 pointer-events-none">
          <div className="absolute -top-10 -left-10 w-60 h-60 rounded-full bg-white/20 blur-2xl" />
          <div className="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-white/10 blur-3xl" />
        </div>
        <div className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 text-center">
          <ScrollReveal variant="fade-up">
            <div className="text-[10px] font-black tracking-[0.3em] text-white/70 mb-3">START YOUR JOURNEY</div>
            <TextReveal as="h2" text="開啟你的仙女旅程" className="text-3xl sm:text-5xl font-bold text-white mb-4" stagger={80} />
            <p className="text-white/90 mb-10 max-w-xl mx-auto text-base sm:text-lg leading-relaxed">
              每一次下單、每一次登入、每一筆評論，<br />
              都讓芽芽和妳一起成長。
            </p>
            <div className="flex flex-wrap gap-4 justify-center">
              <MagneticButton
                href="/products"
                className="px-10 py-4 bg-white text-[#9F6B3E] font-black rounded-full shadow-xl hover:bg-[#fdf7ef] min-h-[52px]"
              >
                🌿 前往選購
              </MagneticButton>
              <MagneticButton
                href="/account"
                className="px-10 py-4 bg-white/10 backdrop-blur border-2 border-white/40 text-white font-black rounded-full hover:bg-white/20 min-h-[52px]"
                strength={0.25}
              >
                🌱 我的仙女任務
              </MagneticButton>
            </div>
          </ScrollReveal>
        </div>
      </section>
    </>
  );
}
