import type { MetadataRoute } from 'next';
import { fetchApi, imageUrl, type Product, type Article, type ProductCategory } from '@/lib/api';
import { SITE_URL } from '@/lib/site';

// Render at runtime on the prod server, not at CI build time. A previous
// silent-fail at build (Cloudflare/network blocked the GH runner) baked an
// 11-URL sitemap into .next and shipped it for weeks — Google never saw
// the 450+ product/article URLs. With ISR the prod server regenerates
// hourly using the in-network API call, which is far more reliable than
// CI build → API round trip through Cloudflare.
export const revalidate = 3600;

interface Campaign {
  id: number;
  slug: string;
  name: string;
  start_at: string;
  end_at: string;
  is_running: boolean;
}

interface PaginatedArticles {
  data: Article[];
  current_page: number;
  last_page: number;
}

/**
 * Paginate through the /articles endpoint until all pages have been read.
 * `per_page=100` is an API-side cap; using it minimizes round trips.
 */
async function fetchAllArticles(): Promise<Article[]> {
  const all: Article[] = [];
  let page = 1;
  // Safety cap so a buggy API can't spin forever.
  for (let i = 0; i < 20; i++) {
    const result = await fetchApi<PaginatedArticles>(`/articles?per_page=100&page=${page}`);
    all.push(...result.data);
    if (page >= result.last_page) break;
    page++;
  }
  return all;
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const baseUrl = SITE_URL;

  const staticPages: MetadataRoute.Sitemap = [
    { url: baseUrl, lastModified: new Date(), changeFrequency: 'daily', priority: 1 },
    { url: `${baseUrl}/products`, lastModified: new Date(), changeFrequency: 'daily', priority: 0.9 },
    { url: `${baseUrl}/articles`, lastModified: new Date(), changeFrequency: 'weekly', priority: 0.7 },
    { url: `${baseUrl}/about`, lastModified: new Date(), changeFrequency: 'monthly', priority: 0.7 },
    { url: `${baseUrl}/join`, lastModified: new Date(), changeFrequency: 'monthly', priority: 0.7 },
    { url: `${baseUrl}/reviews`, lastModified: new Date(), changeFrequency: 'weekly', priority: 0.8 },
    { url: `${baseUrl}/where-to-buy`, lastModified: new Date(), changeFrequency: 'monthly', priority: 0.8 },
    { url: `${baseUrl}/faq`, lastModified: new Date(), changeFrequency: 'monthly', priority: 0.6 },
    { url: `${baseUrl}/privacy`, lastModified: new Date(), changeFrequency: 'yearly', priority: 0.3 },
    { url: `${baseUrl}/return-policy`, lastModified: new Date(), changeFrequency: 'yearly', priority: 0.3 },
    { url: `${baseUrl}/terms`, lastModified: new Date(), changeFrequency: 'yearly', priority: 0.3 },
  ];

  // Products — include hero + gallery images for Google Image search.
  // Cap images at 10/URL (Google's effective limit) to avoid bloating the sitemap.
  let productPages: MetadataRoute.Sitemap = [];
  try {
    const products = await fetchApi<Product[]>('/products');
    productPages = products.map((p) => {
      const imgs = [imageUrl(p.image), ...(p.gallery ?? []).map((g) => imageUrl(g))]
        .filter((u): u is string => !!u)
        .slice(0, 10);
      return {
        url: `${baseUrl}/products/${p.slug}`,
        lastModified: new Date(),
        changeFrequency: 'weekly' as const,
        priority: 0.8,
        ...(imgs.length > 0 ? { images: imgs } : {}),
      };
    });
  } catch (e) {
    console.error('[sitemap] products fetch failed:', e);
  }

  // Articles — paginate through ALL pages (~430+ entries).
  let articlePages: MetadataRoute.Sitemap = [];
  try {
    const articles = await fetchAllArticles();
    articlePages = articles.map((a) => {
      const img = imageUrl(a.featured_image);
      return {
        url: `${baseUrl}/articles/${a.slug}`,
        lastModified: a.published_at ? new Date(a.published_at) : new Date(),
        changeFrequency: 'monthly' as const,
        priority: 0.6,
        ...(img ? { images: [img] } : {}),
      };
    });
  } catch (e) {
    console.error('[sitemap] articles fetch failed:', e);
  }

  let categoryPages: MetadataRoute.Sitemap = [];
  try {
    const categories = await fetchApi<ProductCategory[]>('/product-categories');
    categoryPages = categories
      .filter((c) => c.name !== '未分類' && (c.products_count ?? 0) > 0)
      .map((c) => ({
        url: `${baseUrl}/products/category/${c.slug}`,
        lastModified: new Date(),
        changeFrequency: 'weekly' as const,
        priority: 0.8,
      }));
  } catch (e) {
    console.error('[sitemap] categories fetch failed:', e);
  }

  let campaignPages: MetadataRoute.Sitemap = [];
  try {
    const campaigns = await fetchApi<Campaign[]>('/campaigns');
    campaignPages = campaigns
      .filter((c) => c.is_running)
      .map((c) => ({
        url: `${baseUrl}/campaigns/${c.slug}`,
        lastModified: new Date(c.start_at),
        changeFrequency: 'weekly' as const,
        priority: 0.7,
      }));
  } catch (e) {
    console.error('[sitemap] campaigns fetch failed:', e);
  }

  return [...staticPages, ...categoryPages, ...productPages, ...articlePages, ...campaignPages];
}
