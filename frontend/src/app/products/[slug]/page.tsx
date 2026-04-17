import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import { getProduct, getProducts, imageUrl } from '@/lib/api';
import { formatPrice } from '@/lib/format';
import AddToCartButton from '@/components/AddToCartButton';
import { HealthFoodBadge } from '@/components/HealthFoodBadge';
import ProductStickyCTA from '@/components/ProductStickyCTA';
import RecentlyViewed from '@/components/RecentlyViewed';
import RecentlyViewedTracker from '@/components/RecentlyViewedTracker';
import RelatedArticles from '@/components/RelatedArticles';
import StockNotifyButton from '@/components/StockNotifyButton';
import ProductGallery from '@/components/ProductGallery';
import ShareButtons from '@/components/ShareButtons';
import CrossSellAddOn from '@/components/CrossSellAddOn';
import SiteIcon from '@/components/SiteIcon';
import CampaignPricing from '@/components/CampaignPricing';
import { sanitizeHtml } from '@/lib/sanitize';
import type { Product } from '@/lib/api';
import { breadcrumbSchema, productSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 3600;

export async function generateStaticParams() {
  try {
    const products = await getProducts();
    return products.map((p) => ({ slug: p.slug }));
  } catch {
    return [];
  }
}

type Props = {
  params: Promise<{ slug: string }>;
};

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { slug } = await params;
  try {
    const product = await getProduct(slug);
    return {
      title: product.seo_meta?.title || product.name,
      description: product.seo_meta?.description || product.short_description,
      alternates: { canonical: `/products/${product.slug}` },
      openGraph: {
        type: 'website',
        title: product.name,
        description: product.short_description,
        images: product.seo_meta?.og_image
          ? [imageUrl(product.seo_meta.og_image)!]
          : product.image
            ? [imageUrl(product.image)!]
            : [],
      },
    };
  } catch {
    return { title: '商品不存在' };
  }
}

function ShortDescription({ text }: { text: string }) {
  // Extract only the ▲ feature lines as highlights
  const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
  const highlights = lines.filter((l) => l.startsWith('▲'));

  if (highlights.length > 0) {
    return (
      <ul className="text-gray-600 mb-6 leading-relaxed space-y-1 text-sm">
        {highlights.map((line, i) => (
          <li key={i} className="flex items-start gap-1.5">
            <span className="text-[#9F6B3E] shrink-0">▲</span>
            <span>{line.replace(/^▲\s*/, '')}</span>
          </li>
        ))}
      </ul>
    );
  }

  // Fallback: show first 3 lines
  const preview = lines.slice(0, 3).join('\n');
  return (
    <p className="text-gray-600 mb-6 leading-relaxed text-sm whitespace-pre-line">
      {preview}
    </p>
  );
}

export default async function ProductDetailPage({ params }: Props) {
  const { slug } = await params;

  let product: Product | null = null;
  try {
    product = await getProduct(slug);
  } catch {
    // fall through to notFound below
  }
  if (!product) {
    notFound();
  }
  // Legacy URL hit — 308 permanent redirect to the canonical slug.
  // Normalize both sides: params.slug may arrive URL-encoded from Next depending on runtime.
  const canonical = product.slug;
  const current = decodeURIComponent(slug);
  if (canonical !== current) {
    permanentRedirect(`/products/${canonical}`);
  }

  // Fetch related products
  let relatedProducts: Product[] = [];
  try {
    const allProducts = await getProducts();
    const others = allProducts.filter((p) => p.id !== product.id);
    const categoryIds = product.categories.map((c) => c.id);
    const sameCategory = others.filter((p) =>
      p.categories.some((c) => categoryIds.includes(c.id))
    );
    if (sameCategory.length >= 4) {
      relatedProducts = sameCategory.slice(0, 4);
    } else if (sameCategory.length > 0) {
      const remaining = others.filter((p) => !sameCategory.includes(p));
      relatedProducts = [
        ...sameCategory,
        ...remaining.sort(() => Math.random() - 0.5),
      ].slice(0, 4);
    } else {
      relatedProducts = others.sort(() => Math.random() - 0.5).slice(0, 4);
    }
  } catch {
    // Ignore
  }

  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';
  const prodJsonLd = productSchema({
    name: product.name,
    description: product.short_description || product.name,
    image: product.image ? imageUrl(product.image) : null,
    slug: product.slug,
    price: product.price,
    isActive: product.is_active,
    sku: product.slug,
  });
  const breadcrumbs = breadcrumbSchema([
    { name: '首頁', url: '/' },
    { name: '全館商品', url: '/products' },
    ...(product.categories[0]
      ? [{ name: product.categories[0].name, url: `/products?category=${product.categories[0].slug}` }]
      : []),
    { name: product.name },
  ]);

  return (
    <>
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: jsonLdScript(prodJsonLd, breadcrumbs) }}
    />
    <div className="max-w-[1290px] mx-auto px-5 sm:px-6 lg:px-8 py-6 sm:py-10 pb-[calc(6rem+env(safe-area-inset-bottom))] md:pb-10">
      {/* Breadcrumb bar (visual, complements JSON-LD) */}
      <nav className="hidden sm:flex items-center gap-2 text-xs text-gray-500 mb-5 overflow-hidden" aria-label="麵包屑">
        <Link href="/" className="hover:text-[#9F6B3E]">首頁</Link>
        <span className="text-gray-300">/</span>
        <Link href="/products" className="hover:text-[#9F6B3E]">全館商品</Link>
        {product.categories[0] && (
          <>
            <span className="text-gray-300">/</span>
            <Link href={`/products?category=${product.categories[0].slug}`} className="hover:text-[#9F6B3E]">
              {product.categories[0].name}
            </Link>
          </>
        )}
        <span className="text-gray-300">/</span>
        <span className="text-gray-700 truncate">{product.name}</span>
      </nav>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 lg:items-start">
        {/* Image Section */}
        <ProductGallery
          mainImage={product.image}
          gallery={product.gallery}
          productName={product.name}
        />

        {/* Product Info — sticky on desktop so CTA stays reachable */}
        <div className="lg:sticky lg:top-24">
          {/* Categories */}
          {product.categories.length > 0 && (
            <div className="flex flex-wrap gap-2 mb-3">
              {product.categories.map((cat) => (
                <span
                  key={cat.id}
                  className="text-xs font-medium text-[#9F6B3E] bg-[#9F6B3E]/10 px-2 py-1 rounded-full"
                >
                  {cat.name}
                </span>
              ))}
            </div>
          )}

          <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-4">
            {product.name}
          </h1>

          {product.short_description && !product.active_campaign && (
            <ShortDescription text={product.short_description} />
          )}

          {product.hf_cert_no && product.hf_cert_claim && (
            <div className="mb-5">
              <HealthFoodBadge certNo={product.hf_cert_no} claim={product.hf_cert_claim} />
            </div>
          )}

          {/* Pricing: campaign deal OR 3-tier */}
          {product.active_campaign ? (
            <CampaignPricing campaign={product.active_campaign} originalPrice={product.price} shortDescription={product.short_description} />
          ) : (
            <div className="mb-6">
              <div className="flex items-center justify-between mb-3">
                <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">PRICING · 三階梯</div>
                <a href="/#pricing" className="text-[10px] text-gray-400 underline">規則說明</a>
              </div>
              <div className="grid grid-cols-3 gap-2 items-stretch">
                {/* Tier 1 */}
                <div className="relative pt-2">
                  <div className="rounded-2xl bg-white border border-[#e7d9cb] p-3 text-center h-full">
                    <div className="flex items-center justify-center gap-1 text-[10px] font-black text-gray-400 tracking-wider mb-1">
                      <SiteIcon name="sprout" size={14} className="inline" />階梯 1
                    </div>
                    <div className="text-[10px] text-gray-500 mb-0.5">單件</div>
                    <div className="text-xl sm:text-2xl font-black text-[#c0392b]">
                      {formatPrice(product.price)}
                    </div>
                  </div>
                </div>

                {/* Tier 2 — combo */}
                {product.combo_price ? (
                  <div className="relative pt-2">
                    <span className="absolute top-0 left-1/2 -translate-x-1/2 z-10 px-2 py-0.5 rounded-full bg-[#9F6B3E] text-white text-[9px] font-black tracking-wide shadow-sm whitespace-nowrap">
                      最多人選
                    </span>
                    <div className="rounded-2xl bg-gradient-to-br from-[#fdf7ef] to-[#f7eee3] border-2 border-[#9F6B3E]/40 p-3 text-center shadow-sm shadow-[#9F6B3E]/15 h-full">
                      <div className="flex items-center justify-center gap-1 text-[10px] font-black text-[#9F6B3E] tracking-wider mb-1">
                        <SiteIcon name="ribbon" size={14} className="inline" />階梯 2
                      </div>
                      <div className="text-[10px] text-[#9F6B3E]/80 mb-0.5">任選 2 件</div>
                      <div className="text-xl sm:text-2xl font-black text-[#c0392b]">
                        {formatPrice(product.combo_price)}
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="relative pt-2">
                    <div className="rounded-2xl bg-slate-50 border border-slate-200 p-3 text-center opacity-50 h-full">
                      <div className="text-[10px] text-slate-400">—</div>
                    </div>
                  </div>
                )}

                {/* Tier 3 — VIP */}
                {product.vip_price ? (
                  <div className="relative pt-2">
                    <div className="rounded-2xl bg-gradient-to-br from-[#9F6B3E] via-[#8d5f36] to-[#6b4424] text-white p-3 text-center relative overflow-hidden shadow-md shadow-[#9F6B3E]/30 h-full">
                      <span className="absolute -top-6 -right-6 w-16 h-16 rounded-full bg-white/10" />
                      <div className="relative flex items-center justify-center gap-1 text-[10px] font-black text-[#fcd561] tracking-wider mb-1">
                        <SiteIcon name="crown" size={14} className="inline" />階梯 3
                      </div>
                      <div className="relative text-[10px] text-white/70 mb-0.5">滿 $4,000</div>
                      <div className="relative text-lg sm:text-xl font-black">
                        {formatPrice(product.vip_price)}
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="relative pt-2">
                    <div className="rounded-2xl bg-slate-50 border border-slate-200 p-3 text-center opacity-50 h-full">
                      <div className="text-[10px] text-slate-400">—</div>
                    </div>
                  </div>
                )}
              </div>

              {/* Contextual upgrade hint */}
              {product.combo_price && product.vip_price && (
                <div className="mt-3 flex items-center gap-2 text-[11px] text-[#7a5836] bg-[#fdf7ef] border border-[#e7d9cb] rounded-xl px-3 py-2">
                  <SiteIcon name="sparkle" size={14} className="inline" />
                  <span className="flex-1">
                    整車搭配越多越省 — 滿 $4,000 自動升級 VIP 價
                    <strong className="text-[#9F6B3E] ml-1">
                      省 {formatPrice(product.price - product.vip_price)}/件
                    </strong>
                  </span>
                </div>
              )}
            </div>
          )}

          {/* Stock status + qty + add-to-cart — desktop only; mobile uses sticky bottom bar */}
          <div className="hidden md:block">
            {product.stock_status === 'outofstock' ? (
              <div className="space-y-3">
                <span className="inline-block bg-gray-500 text-white text-sm font-semibold px-4 py-1.5 rounded-full">
                  已售完
                </span>
                <StockNotifyButton slug={product.slug} />
              </div>
            ) : (
              <AddToCartButton product={product} />
            )}
          </div>
          {/* Mobile: sold-out products also get notify-me (sticky CTA disabled for out-of-stock) */}
          {product.stock_status === 'outofstock' && (
            <div className="md:hidden mt-4">
              <StockNotifyButton slug={product.slug} />
            </div>
          )}

          {/* Share */}
          <ShareButtons
            url={`${siteUrl}/products/${product.slug}`}
            title={product.name}
          />
        </div>
      </div>

      {/* Cross-sell add-on with cart progress bar */}
      {relatedProducts.length > 0 && (
        <CrossSellAddOn products={relatedProducts} />
      )}

      {/* Recently viewed — only shows if user has history (localStorage) */}
      <RecentlyViewed excludeSlug={product.slug} />

      {/* Internal cross-link: recent articles, boosts SEO + dwell time */}
      <RelatedArticles />

      {/* Description — full width container, readable prose width */}
      {product.description && (
        <section className="mt-12 pt-10 border-t border-gray-200">
          <div className="max-w-3xl mx-auto">
            <div className="text-[10px] font-black tracking-[0.2em] text-[#7a5836]">PRODUCT · 商品說明</div>
            <h2 className="text-xl sm:text-2xl font-black text-gray-900 mt-1 mb-6">深入認識</h2>
            <div
              className="prose-product text-gray-700"
              dangerouslySetInnerHTML={{ __html: sanitizeHtml(product.description) }}
            />
          </div>
        </section>
      )}

    </div>

    {/* Track view in recently-viewed localStorage */}
    <RecentlyViewedTracker
      slug={product.slug}
      name={product.name}
      image={product.image}
      price={product.price}
    />

    {/* Mobile sticky CTA — fixed bottom, full width */}
    <ProductStickyCTA product={product} />
    </>
  );
}
