import { notFound } from 'next/navigation';
import { imageUrl, getBundle } from '@/lib/api';
import ImageWithFallback from '@/components/ImageWithFallback';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import SiteIcon from '@/components/SiteIcon';
import CampaignBundleCard from '@/components/CampaignBundleCard';
import { jsonLdScript, breadcrumbSchema } from '@/lib/jsonld';
import { SITE_URL } from '@/lib/site';

export const dynamic = 'force-dynamic';

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  try {
    const b = await getBundle(slug);
    const ogImage = b.image ? imageUrl(b.image) : undefined;
    return {
      title: b.name,
      description: b.description || `${b.name} — 活動限時優惠，加入購物車即享整車 VIP 價`,
      alternates: { canonical: `/bundles/${slug}` },
      openGraph: {
        title: b.name,
        description: b.description || `${b.name} — 活動限時優惠`,
        ...(ogImage ? { images: [ogImage] } : {}),
      },
    };
  } catch {
    return { title: '活動限時優惠不存在' };
  }
}

export default async function BundlePage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;

  let bundle;
  try {
    bundle = await getBundle(slug);
  } catch {
    notFound();
  }
  if (!bundle) notFound();

  const campaign = bundle.campaign;
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    ...(campaign ? [{ name: campaign.name, url: `/campaigns/${campaign.slug}` }] : []),
    { name: bundle.name },
  ]);

  const offerSchema = {
    '@type': 'Offer',
    name: bundle.name,
    description: bundle.description || bundle.name,
    price: bundle.bundle_price,
    priceCurrency: 'TWD',
    availability: 'https://schema.org/InStock',
    ...(campaign ? { validThrough: campaign.end_at } : {}),
    url: `${SITE_URL}/bundles/${slug}`,
  };

  return (
    <div className="min-h-screen">
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: jsonLdScript(offerSchema, breadcrumbs) }}
      />

      {/* Hero — 大圖 (object-contain 避免裁切) + 資訊並排 */}
      <section className="relative bg-gradient-to-br from-[#9F6B3E] via-[#c9935a] to-[#85572F] text-white overflow-hidden">
        <FloatingShapes />
        {/* 彩色 SVG 裝飾 — 左上星芒 + 右下泡泡，用亮色點綴 brown gradient */}
        <svg aria-hidden className="absolute top-6 left-4 sm:top-10 sm:left-10 w-20 h-20 sm:w-28 sm:h-28 opacity-80" viewBox="0 0 100 100">
          <defs>
            <radialGradient id="sparkle-a" cx="0.5" cy="0.5" r="0.5">
              <stop offset="0%" stopColor="#FFE58A" />
              <stop offset="60%" stopColor="#F6B94E" />
              <stop offset="100%" stopColor="#E07A3A" stopOpacity="0.7" />
            </radialGradient>
          </defs>
          <path d="M50 5 L58 42 L95 50 L58 58 L50 95 L42 58 L5 50 L42 42 Z" fill="url(#sparkle-a)" opacity="0.85"/>
        </svg>
        <svg aria-hidden className="absolute bottom-4 right-6 sm:bottom-10 sm:right-12 w-16 h-16 sm:w-24 sm:h-24 opacity-90" viewBox="0 0 100 100">
          <defs>
            <radialGradient id="sparkle-b" cx="0.3" cy="0.3" r="0.7">
              <stop offset="0%" stopColor="#FFCFE2" />
              <stop offset="60%" stopColor="#F49AB9" />
              <stop offset="100%" stopColor="#c0392b" stopOpacity="0.65" />
            </radialGradient>
          </defs>
          <circle cx="50" cy="50" r="38" fill="url(#sparkle-b)" opacity="0.75"/>
          <circle cx="75" cy="30" r="10" fill="#FFE58A" opacity="0.85"/>
        </svg>

        <div className="relative max-w-5xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
          <ScrollReveal>
            <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)] gap-8 md:gap-10 items-center">
              {/* Image — object-contain 保留整張宣傳圖不裁切 */}
              <div className="relative mx-auto md:mx-0 w-full max-w-[460px] aspect-[4/3] rounded-3xl overflow-hidden shadow-[0_25px_60px_-15px_rgba(0,0,0,0.4)] ring-1 ring-white/15 bg-gradient-to-br from-[#fff8ec] to-[#f7eee3]">
                {bundle.image ? (
                  <ImageWithFallback
                    src={imageUrl(bundle.image)!}
                    alt={bundle.name}
                    fill
                    sizes="(max-width: 768px) 92vw, 460px"
                    className="object-contain p-2"
                    priority
                  />
                ) : (
                  <div className="w-full h-full flex items-center justify-center">
                    <SiteIcon name="fire" size={64} className="text-[#9F6B3E]/40" />
                  </div>
                )}
              </div>

              {/* Info */}
              <div className="text-center md:text-left">
                {/* Campaign chip — 火焰直接坐在紅色 chip 上，不再包白圓，視覺更一體 */}
                {campaign && (
                  <a
                    href={`/campaigns/${campaign.slug}`}
                    className="inline-flex items-center gap-2 pl-3 pr-4 py-2 rounded-full bg-gradient-to-r from-[#c0392b] to-[#e74c3c] shadow-lg shadow-[#c0392b]/30 hover:scale-105 transition-transform mb-5"
                  >
                    {/* 重繪火焰：雙層火舌，外層飽和、內層亮芯，直接落在紅 chip 上 */}
                    <svg
                      width="28"
                      height="32"
                      viewBox="0 0 24 28"
                      fill="none"
                      className="animate-[flame-flicker_1.1s_ease-in-out_infinite] drop-shadow-[0_0_6px_rgba(255,217,61,0.55)] shrink-0"
                      style={{ transformOrigin: '50% 100%' }}
                    >
                      <defs>
                        <linearGradient id="flame-outer" x1="0.5" y1="1" x2="0.5" y2="0">
                          <stop offset="0%" stopColor="#ff9f43"/>
                          <stop offset="55%" stopColor="#ffd93d"/>
                          <stop offset="100%" stopColor="#fff5b5"/>
                        </linearGradient>
                        <linearGradient id="flame-inner" x1="0.5" y1="1" x2="0.5" y2="0">
                          <stop offset="0%" stopColor="#fff5b5"/>
                          <stop offset="100%" stopColor="#ffffff"/>
                        </linearGradient>
                      </defs>
                      {/* 外火舌 — 不對稱波浪邊給「搖曳感」，底部收窄像 flame 從芯噴出 */}
                      <path
                        d="M12 1
                           C 14.2 4.6 16.4 6.8 16.4 10.2
                           C 16.4 11.8 15.7 12.6 15.3 13.4
                           C 16.8 13.6 18.8 12 19 10.2
                           C 20.4 13.6 20.6 16.4 19.2 19
                           C 17.6 22.2 14.4 24.2 12 24.2
                           C 9.6 24.2 6.4 22.2 4.8 19
                           C 3.4 16.4 3.6 13.6 5 10.2
                           C 5.2 12 7.2 13.6 8.7 13.4
                           C 8.3 12.6 7.6 11.8 7.6 10.2
                           C 7.6 6.8 9.8 4.6 12 1 Z"
                        fill="url(#flame-outer)"
                      />
                      {/* 內芯 — 較瘦，拉高亮度層次 */}
                      <path
                        d="M12 8.5
                           C 13.4 10.8 14 12.6 14 14.6
                           C 14 17 12.8 18.4 12 20.4
                           C 11.2 18.4 10 17 10 14.6
                           C 10 12.6 10.6 10.8 12 8.5 Z"
                        fill="url(#flame-inner)"
                        opacity="0.95"
                      />
                    </svg>
                    <span className="text-sm font-black tracking-wide text-white">{campaign.name}</span>
                    <span className="text-white/70 text-xs font-bold">查看活動 →</span>
                  </a>
                )}
                <h1 className="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                  {bundle.name}
                </h1>
                {bundle.description && (
                  <p className="text-sm sm:text-base text-white/85 mt-4 leading-relaxed max-w-lg mx-auto md:mx-0">
                    {bundle.description}
                  </p>
                )}
                <div className="mt-6 flex flex-wrap justify-center md:justify-start gap-2">
                  <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 backdrop-blur text-xs font-black ring-1 ring-white/20">
                    買 {bundle.buy_items.length} 項
                  </span>
                  {(bundle.gift_items.length + bundle.custom_gifts.length) > 0 && (
                    <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gradient-to-r from-[#e74c3c] to-[#ff6b5b] text-xs font-black shadow-md shadow-[#c0392b]/25">
                      加贈 {bundle.gift_items.length + bundle.custom_gifts.length} 項
                    </span>
                  )}
                  <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-gradient-to-r from-[#f6b94e] to-[#ff9f43] text-[#5a3a1a] text-xs font-black shadow-md shadow-[#9F6B3E]/20">
                    整車 VIP 價
                  </span>
                </div>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Bundle card — 下面接購買卡片（含價格、倒數、加入購物車） */}
      <section className="max-w-2xl mx-auto px-5 sm:px-6 py-8 sm:py-12">
        <CampaignBundleCard bundle={bundle} />
      </section>
    </div>
  );
}
