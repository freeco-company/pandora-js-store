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
                {/* Campaign chip — 移到資訊區頂端、靠近標題。更大火焰 + 搖曳動畫 */}
                {campaign && (
                  <a
                    href={`/campaigns/${campaign.slug}`}
                    className="inline-flex items-center gap-2 pl-1.5 pr-4 py-1.5 rounded-full bg-gradient-to-r from-[#c0392b] to-[#e74c3c] shadow-lg shadow-[#c0392b]/30 hover:scale-105 transition-transform mb-5"
                  >
                    <span className="w-9 h-9 rounded-full bg-white/95 flex items-center justify-center shadow-inner">
                      {/* 彩色火焰 SVG — 大一點 + flicker 動畫 */}
                      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" className="animate-[flame-flicker_1.2s_ease-in-out_infinite]" style={{ transformOrigin: '50% 100%' }}>
                        <defs>
                          <linearGradient id="flame-bundle" x1="0" y1="1" x2="0" y2="0">
                            <stop offset="0%" stopColor="#ff4757"/>
                            <stop offset="50%" stopColor="#ff9f43"/>
                            <stop offset="100%" stopColor="#ffd93d"/>
                          </linearGradient>
                          <linearGradient id="flame-bundle-inner" x1="0" y1="1" x2="0" y2="0">
                            <stop offset="0%" stopColor="#ff9f43"/>
                            <stop offset="100%" stopColor="#ffffff"/>
                          </linearGradient>
                        </defs>
                        <path d="M12 2s4 4 4 8a4 4 0 11-8 0c0-1.5.5-2.5.5-2.5S6 10 6 14a6 6 0 1012 0c0-5-6-12-6-12z" fill="url(#flame-bundle)"/>
                        <path d="M12 9c1.5 1.5 2 3 2 4.5a2 2 0 11-4 0c0-1 .5-2 1-2.5z" fill="url(#flame-bundle-inner)" opacity="0.9"/>
                      </svg>
                    </span>
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
