import { notFound } from 'next/navigation';
import Image from 'next/image';
import { imageUrl } from '@/lib/api';
import ProductCardGrid from '@/components/ProductCardGrid';
import FloatingShapes from '@/components/FloatingShapes';
import ScrollReveal from '@/components/ScrollReveal';
import type { Product } from '@/lib/api';

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

interface Campaign {
  id: number;
  name: string;
  slug: string;
  description: string;
  image: string | null;
  start_at: string;
  end_at: string;
  is_running: boolean;
  products: Product[];
}

export const revalidate = 60;

async function getCampaign(slug: string): Promise<Campaign | null> {
  const res = await fetch(`${API_URL}/campaigns/${slug}`, {
    next: { revalidate: 60, tags: ['campaigns'] },
  });
  if (!res.ok) return null;
  return res.json();
}

export async function generateMetadata({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const campaign = await getCampaign(slug);
  if (!campaign) return { title: '活動不存在' };
  return {
    title: campaign.name,
    description: campaign.description || `${campaign.name} — 限時活動，整車享 VIP 價`,
  };
}

export default async function CampaignPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const campaign = await getCampaign(slug);
  if (!campaign) notFound();

  const endDate = new Date(campaign.end_at);
  const isEnded = endDate <= new Date();

  return (
    <div className="min-h-screen">
      {/* Hero */}
      <section className="relative bg-gradient-to-br from-[#9F6B3E] via-[#c9935a] to-[#85572F] text-white overflow-hidden">
        <FloatingShapes />
        <div className="relative max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-12 sm:py-20">
          <ScrollReveal>
            <div className="text-center max-w-2xl mx-auto">
              {campaign.image && (
                <div className="mb-6 mx-auto w-32 h-32 sm:w-40 sm:h-40 rounded-3xl overflow-hidden shadow-2xl">
                  <Image
                    src={imageUrl(campaign.image)!}
                    alt={campaign.name}
                    width={160}
                    height={160}
                    className="object-cover w-full h-full"
                    priority
                  />
                </div>
              )}
              <div className="text-[10px] font-black tracking-[0.3em] text-white/70">
                {isEnded ? 'EVENT ENDED' : 'LIMITED EVENT'}
              </div>
              <h1 className="text-3xl sm:text-4xl font-black mt-2">{campaign.name}</h1>
              {campaign.description && (
                <p className="text-sm sm:text-base text-white/80 mt-3 leading-relaxed">
                  {campaign.description}
                </p>
              )}
              <div className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/15 backdrop-blur text-xs font-bold">
                <span>🔥</span>
                {isEnded ? (
                  <span>活動已結束</span>
                ) : (
                  <span>
                    活動至 {endDate.toLocaleDateString('zh-TW', { month: 'long', day: 'numeric' })}
                    {' '}· 購物車含活動商品即享 VIP 價
                  </span>
                )}
              </div>
            </div>
          </ScrollReveal>
        </div>
      </section>

      {/* Products grid */}
      {!isEnded && campaign.products.length > 0 && (
        <section className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-12">
          <h2 className="text-xl font-black text-slate-800 mb-6">
            活動商品（{campaign.products.length} 組）
          </h2>
          <ProductCardGrid products={campaign.products} />
        </section>
      )}

      {isEnded && (
        <section className="max-w-2xl mx-auto px-5 py-20 text-center">
          <div className="text-5xl mb-4">🕐</div>
          <h2 className="text-2xl font-black text-slate-800">此活動已結束</h2>
          <p className="text-sm text-slate-500 mt-2">感謝您的參與，期待下次活動！</p>
          <a href="/products" className="inline-flex items-center gap-2 mt-6 px-6 py-3 bg-[#9F6B3E] text-white font-black rounded-full hover:bg-[#85572F] transition-colors">
            瀏覽所有商品 →
          </a>
        </section>
      )}
    </div>
  );
}
