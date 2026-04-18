import type { MetadataRoute } from 'next';
import { fetchApi, type Product, type Article, type ProductCategory } from '@/lib/api';

interface Campaign {
  id: number;
  slug: string;
  name: string;
  start_at: string;
  end_at: string;
  is_running: boolean;
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const baseUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';

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

  // Dynamic product pages
  let productPages: MetadataRoute.Sitemap = [];
  try {
    const products = await fetchApi<Product[]>('/products');
    productPages = products.map((p) => ({
      url: `${baseUrl}/products/${p.slug}`,
      lastModified: new Date(),
      changeFrequency: 'weekly' as const,
      priority: 0.8,
    }));
  } catch {}

  // Dynamic article pages
  let articlePages: MetadataRoute.Sitemap = [];
  try {
    const result = await fetchApi<{ data: Article[] }>('/articles?per_page=200');
    articlePages = result.data.map((a) => ({
      url: `${baseUrl}/articles/${a.slug}`,
      lastModified: a.published_at ? new Date(a.published_at) : new Date(),
      changeFrequency: 'monthly' as const,
      priority: 0.6,
    }));
  } catch {}

  // Category pages
  let categoryPages: MetadataRoute.Sitemap = [];
  try {
    const categories = await fetchApi<ProductCategory[]>('/product-categories');
    categoryPages = categories.map((c) => ({
      url: `${baseUrl}/products/category/${c.slug}`,
      lastModified: new Date(),
      changeFrequency: 'weekly' as const,
      priority: 0.8,
    }));
  } catch {}

  // Dynamic campaign pages
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
  } catch {}

  return [...staticPages, ...categoryPages, ...productPages, ...articlePages, ...campaignPages];
}
