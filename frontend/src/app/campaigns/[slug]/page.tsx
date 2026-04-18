import { notFound } from 'next/navigation';
import Link from 'next/link';
import { imageUrl, getCampaign } from '@/lib/api';
import ImageWithFallback, { LogoPlaceholder } from '@/components/ImageWithFallback';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import SiteIcon from '@/components/SiteIcon';
import { jsonLdScript } from '@/lib/jsonld';
import { SITE_URL } from '@/lib/site';
import { formatPrice } from '@/lib/format';

export const dynamic = 'force-dynamic';

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  try {
    const c = await getCampaign(slug);
    const ogImage = c.image ? imageUrl(c.image) : undefined;
    return {
      title: c.name,
      description: c.description || `${c.name} — 限時活動，整車享 VIP 價`,
      alternates: { canonical: `/campaigns/${slug}` },
      openGraph: {
        title: c.name,
        description: c.description || `${c.name} — 限時活動`,
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
  const eventSchema = {
    '@type': 'Event',
    name: campaign.name,
    description: campaign.description || `${campaign.name} — 限時活動`,
    startDate: campaign.start_at,
    endDate: campaign.end_at,
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OnlineEventAttendanceMode',
    location: { '@type': 'VirtualLocation', url: `${SITE_URL}/campaigns/${slug}` },
    organizer: { '@type': 'Organization', name: '婕樂纖仙女館', url: SITE_URL },
    ...(campaign.image ? { image: imageUrl(campaign.image) } : {}),
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
              <div className="text-[10px] font-black tracking-[0.3em] text-white/70">LIMITED CAMPAIGN</div>
              <h1 className="text-3xl sm:text-4xl font-black mt-2">{campaign.name}</h1>
              {campaign.description && (
                <p className="text-sm sm:text-base text-white/80 mt-3 leading-relaxed">
                  {campaign.description}
                </p>
              )}
              <div className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/15 backdrop-blur text-xs font-bold">
                <SiteIcon name="fire" size={14} />
                <span>
                  活動至 {endDate.toLocaleDateString('zh-TW', { month: 'long', day: 'numeric' })} ·
                  任一套組加入購物車即享整車 VIP 價
                </span>
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Bundles grid */}
      <section className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-10 sm:py-14">
        {campaign.bundles.length === 0 ? (
          <div className="text-center py-16 text-gray-500">
            活動正在準備中，套組即將上架
          </div>
        ) : (
          <>
            <h2 className="text-xl sm:text-2xl font-black text-[#3d2e22] mb-6">
              活動套組（{campaign.bundles.length} 組）
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
              {campaign.bundles.map((b) => {
                const savings = b.bundle_value_price - b.bundle_price;
                const pct = savings > 0 ? Math.round((savings / b.bundle_value_price) * 100) : 0;
                return (
                  <Link
                    key={b.id}
                    href={`/bundles/${b.slug}`}
                    className="group relative block rounded-3xl overflow-hidden bg-white border border-[#e7d9cb] shadow-md shadow-[#9F6B3E]/5 hover:shadow-xl hover:-translate-y-1 transition-all"
                  >
                    <div className="relative aspect-[4/3] bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3]">
                      {b.image ? (
                        <ImageWithFallback
                          src={imageUrl(b.image)!}
                          alt={b.name}
                          fill
                          sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                          className="object-cover transition-transform duration-500 group-hover:scale-105"
                        />
                      ) : (
                        <LogoPlaceholder />
                      )}
                      {pct > 0 && (
                        <div className="absolute top-3 right-3 px-2.5 py-1 rounded-full bg-[#c0392b] text-white text-xs font-black shadow-md">
                          省 {pct}%
                        </div>
                      )}
                    </div>
                    <div className="p-5">
                      <h3 className="text-base sm:text-lg font-black text-[#3d2e22] line-clamp-2 mb-1">
                        {b.name}
                      </h3>
                      {b.description && (
                        <p className="text-xs text-[#7a5836]/70 line-clamp-2 mb-3">{b.description}</p>
                      )}
                      <div className="flex items-baseline gap-2 mt-3 flex-wrap">
                        <span className="text-2xl font-black text-[#c0392b]">
                          {formatPrice(b.bundle_price)}
                        </span>
                        {savings > 0 && (
                          <span className="inline-flex items-baseline gap-1 text-sm text-gray-400">
                            <span className="text-[10px] font-bold">價值</span>
                            <span className="line-through">{formatPrice(b.bundle_value_price)}</span>
                          </span>
                        )}
                      </div>
                      <div className="mt-3 flex items-center justify-between text-xs">
                        <span className="text-[#9F6B3E] font-bold">
                          買 {b.buy_items.length} {b.gift_items.length > 0 && `・送 ${b.gift_items.length}`}
                        </span>
                        <span className="text-[#9F6B3E] font-black group-hover:translate-x-1 transition-transform">
                          查看 →
                        </span>
                      </div>
                    </div>
                  </Link>
                );
              })}
            </div>
          </>
        )}
      </section>
    </div>
  );
}
