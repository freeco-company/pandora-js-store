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
      description: b.description || `${b.name} — 限時套組，加入購物車即享整車 VIP 價`,
      alternates: { canonical: `/bundles/${slug}` },
      openGraph: {
        title: b.name,
        description: b.description || `${b.name} — 限時套組`,
        ...(ogImage ? { images: [ogImage] } : {}),
      },
    };
  } catch {
    return { title: '套組不存在' };
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

      {/* Hero — 大圖 + 資訊並排（桌機），手機上下疊 */}
      <section className="relative bg-gradient-to-br from-[#9F6B3E] via-[#c9935a] to-[#85572F] text-white overflow-hidden">
        <FloatingShapes />
        <div className="relative max-w-5xl mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-14 lg:py-16">
          <ScrollReveal>
            <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)] gap-8 md:gap-12 items-center">
              {/* Image */}
              {bundle.image ? (
                <div className="relative mx-auto md:mx-0 w-full max-w-[380px] md:max-w-none aspect-square rounded-3xl overflow-hidden shadow-[0_25px_60px_-15px_rgba(0,0,0,0.4)] ring-1 ring-white/10">
                  <ImageWithFallback
                    src={imageUrl(bundle.image)!}
                    alt={bundle.name}
                    fill
                    sizes="(max-width: 768px) 85vw, 400px"
                    className="object-cover"
                    priority
                  />
                </div>
              ) : (
                <div className="mx-auto md:mx-0 w-full max-w-[380px] aspect-square rounded-3xl bg-white/10 flex items-center justify-center">
                  <SiteIcon name="fire" size={64} className="text-white/40" />
                </div>
              )}

              {/* Info */}
              <div className="text-center md:text-left">
                {campaign && (
                  <a
                    href={`/campaigns/${campaign.slug}`}
                    className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur text-[11px] font-black tracking-[0.15em] text-white/90 hover:bg-white/15 transition-colors mb-4"
                  >
                    <SiteIcon name="fire" size={12} />
                    {campaign.name}
                    <span className="opacity-60">←</span>
                  </a>
                )}
                <h1 className="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight">
                  {bundle.name}
                </h1>
                {bundle.description && (
                  <p className="text-sm sm:text-base text-white/80 mt-4 leading-relaxed max-w-lg mx-auto md:mx-0">
                    {bundle.description}
                  </p>
                )}
                {/* inline quick facts */}
                <div className="mt-6 flex flex-wrap justify-center md:justify-start gap-2">
                  <span className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur text-xs font-bold">
                    買 {bundle.buy_items.length} 項
                  </span>
                  {bundle.gift_items.length > 0 && (
                    <span className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-[#e74c3c]/30 backdrop-blur text-xs font-bold border border-white/20">
                      加贈 {bundle.gift_items.length} 項
                    </span>
                  )}
                  <span className="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-white/10 backdrop-blur text-xs font-bold">
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
