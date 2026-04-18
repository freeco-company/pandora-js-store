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
      {jsonLdScript(offerSchema, breadcrumbs)}

      {/* Hero */}
      <section className="relative bg-gradient-to-br from-[#9F6B3E] via-[#c9935a] to-[#85572F] text-white overflow-hidden">
        <FloatingShapes />
        <div className="relative max-w-2xl mx-auto px-5 sm:px-6 py-10 sm:py-14">
          <ScrollReveal>
            <div className="text-center">
              {bundle.image && (
                <div className="mb-5 mx-auto w-28 h-28 sm:w-32 sm:h-32 rounded-3xl overflow-hidden shadow-2xl">
                  <ImageWithFallback
                    src={imageUrl(bundle.image)!}
                    alt={bundle.name}
                    width={128}
                    height={128}
                    className="object-cover w-full h-full"
                    priority
                  />
                </div>
              )}
              {campaign && (
                <a
                  href={`/campaigns/${campaign.slug}`}
                  className="inline-flex items-center gap-1 text-[10px] font-black tracking-[0.2em] text-white/70 hover:text-white mb-2"
                >
                  <SiteIcon name="fire" size={12} />
                  {campaign.name} ←
                </a>
              )}
              <h1 className="text-2xl sm:text-3xl font-black mt-1">{bundle.name}</h1>
              {bundle.description && (
                <p className="text-sm sm:text-base text-white/80 mt-3 leading-relaxed">{bundle.description}</p>
              )}
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Bundle card */}
      <section className="max-w-2xl mx-auto px-5 sm:px-6 py-8 sm:py-12">
        <CampaignBundleCard bundle={bundle} />
      </section>
    </div>
  );
}
