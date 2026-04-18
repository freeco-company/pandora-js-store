import { notFound } from 'next/navigation';
import { imageUrl, getCampaign } from '@/lib/api';
import ImageWithFallback from '@/components/ImageWithFallback';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import SiteIcon from '@/components/SiteIcon';
import CampaignBundleCard from '@/components/CampaignBundleCard';
import { jsonLdScript } from '@/lib/jsonld';
import { formatPrice } from '@/lib/format';

export const revalidate = 60;

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  try {
    const c = await getCampaign(slug);
    const ogImage = c.image ? imageUrl(c.image) : undefined;
    return {
      title: c.name,
      description: c.description || `${c.name} — 限時套組，整車享 VIP 價`,
      alternates: { canonical: `/campaigns/${slug}` },
      openGraph: {
        title: c.name,
        description: c.description || `${c.name} — 限時套組`,
        ...(ogImage ? { images: [ogImage] } : {}),
      },
    };
  } catch {
    return { title: '活動不存在' };
  }
}

export default async function CampaignPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;

  let campaign;
  try {
    campaign = await getCampaign(slug);
  } catch {
    notFound();
  }
  if (!campaign) notFound();

  const endDate = new Date(campaign.end_at);
  const siteUrl = 'https://pandora.js-store.com.tw';
  const eventSchema = {
    '@type': 'Event',
    name: campaign.name,
    description: campaign.description || `${campaign.name} — 限時套組`,
    startDate: campaign.start_at,
    endDate: campaign.end_at,
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OnlineEventAttendanceMode',
    location: { '@type': 'VirtualLocation', url: `${siteUrl}/campaigns/${slug}` },
    organizer: { '@type': 'Organization', name: '婕樂纖仙女館', url: siteUrl },
    ...(campaign.image ? { image: imageUrl(campaign.image) } : {}),
    offers: {
      '@type': 'Offer',
      price: campaign.bundle_price,
      priceCurrency: 'TWD',
      availability: 'https://schema.org/InStock',
      validThrough: campaign.end_at,
      url: `${siteUrl}/campaigns/${slug}`,
    },
  };

  return (
    <div className="min-h-screen">
      {jsonLdScript(eventSchema)}

      {/* Hero */}
      <section className="relative bg-gradient-to-br from-[#9F6B3E] via-[#c9935a] to-[#85572F] text-white overflow-hidden">
        <FloatingShapes />
        <div className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-12 sm:py-20">
          <ScrollReveal>
            <div className="text-center max-w-2xl mx-auto">
              {campaign.image && (
                <div className="mb-6 mx-auto w-32 h-32 sm:w-40 sm:h-40 rounded-3xl overflow-hidden shadow-2xl">
                  <ImageWithFallback
                    src={imageUrl(campaign.image)!}
                    alt={campaign.name}
                    width={160}
                    height={160}
                    className="object-cover w-full h-full"
                    priority
                  />
                </div>
              )}
              <div className="text-[10px] font-black tracking-[0.3em] text-white/70">LIMITED BUNDLE</div>
              <h1 className="text-3xl sm:text-4xl font-black mt-2">{campaign.name}</h1>
              {campaign.description && (
                <p className="text-sm sm:text-base text-white/80 mt-3 leading-relaxed">{campaign.description}</p>
              )}
              <div className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/15 backdrop-blur text-xs font-bold">
                <SiteIcon name="fire" size={14} />
                <span>
                  活動至 {endDate.toLocaleDateString('zh-TW', { month: 'long', day: 'numeric' })} ·
                  套組加入購物車即享 VIP 價
                </span>
              </div>
              <div className="mt-3 text-[11px] text-white/70 font-mono">
                套組價 {formatPrice(campaign.bundle_price)}
                {campaign.bundle_original_price > campaign.bundle_price && (
                  <>
                    {' '}
                    <span className="line-through">{formatPrice(campaign.bundle_original_price)}</span>
                  </>
                )}
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Bundle card */}
      <section className="max-w-2xl mx-auto px-5 sm:px-6 py-10 sm:py-14">
        <CampaignBundleCard bundle={campaign} />
      </section>
    </div>
  );
}
