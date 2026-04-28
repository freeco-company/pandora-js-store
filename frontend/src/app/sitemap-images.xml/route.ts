import { fetchApi, imageUrl, type Product, type Article } from '@/lib/api';
import { SITE_URL } from '@/lib/site';

/**
 * Image sitemap for Google Image search discovery.
 * Separate file per Google recommendation (easier to diagnose image-only indexing).
 *
 * force-dynamic for the same reason as sitemap.ts: build-time prerender +
 * silent fetch failure = empty sitemap shipped forever. Render at runtime.
 */
export const dynamic = 'force-dynamic';

export async function GET() {
  const baseUrl = SITE_URL;

  const entries: Array<{ page: string; images: Array<{ loc: string; title: string }> }> = [];

  // Products
  try {
    const products = await fetchApi<Product[]>('/products');
    for (const p of products) {
      const imgs: Array<{ loc: string; title: string }> = [];
      if (p.image) {
        const url = imageUrl(p.image);
        if (url) imgs.push({ loc: url, title: p.name });
      }
      if (p.gallery) {
        for (const g of p.gallery) {
          const url = imageUrl(g);
          if (url) imgs.push({ loc: url, title: p.name });
        }
      }
      if (imgs.length > 0) {
        entries.push({ page: `${baseUrl}/products/${p.slug}`, images: imgs });
      }
    }
  } catch (e) {
    console.error('[sitemap-images] products fetch failed:', e);
  }

  // Articles
  try {
    const res = await fetchApi<{ data: Article[] }>('/articles?per_page=500');
    for (const a of res.data) {
      if (!a.featured_image) continue;
      const url = imageUrl(a.featured_image);
      if (!url) continue;
      entries.push({
        page: `${baseUrl}/articles/${a.slug}`,
        images: [{ loc: url, title: a.title }],
      });
    }
  } catch (e) {
    console.error('[sitemap-images] articles fetch failed:', e);
  }

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
${entries
  .map(
    (e) => `  <url>
    <loc>${escape(e.page)}</loc>
${e.images
  .map(
    (img) => `    <image:image>
      <image:loc>${escape(img.loc)}</image:loc>
      <image:title>${escape(img.title)}</image:title>
    </image:image>`,
  )
  .join('\n')}
  </url>`,
  )
  .join('\n')}
</urlset>`;

  return new Response(xml, {
    headers: { 'Content-Type': 'application/xml; charset=utf-8' },
  });
}

function escape(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}
