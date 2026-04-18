import { fetchApi, imageUrl, type Article } from '@/lib/api';
import { SITE_URL } from '@/lib/site';

const siteUrl = SITE_URL;

export const revalidate = 3600;

export async function GET() {
  let articles: Article[] = [];
  try {
    const result = await fetchApi<{ data: Article[] }>('/articles?per_page=50');
    articles = result.data;
  } catch {
    // empty feed on error
  }

  const escapeXml = (s: string) =>
    s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  const items = articles.map((a) => {
    const pubDate = new Date(a.published_at).toUTCString();
    const link = `${siteUrl}/articles/${a.slug}`;
    const img = a.featured_image ? imageUrl(a.featured_image) : null;
    return `    <item>
      <title>${escapeXml(a.title)}</title>
      <link>${escapeXml(link)}</link>
      <guid isPermaLink="true">${escapeXml(link)}</guid>
      <pubDate>${pubDate}</pubDate>
      <description>${escapeXml(a.excerpt || a.title)}</description>${img ? `\n      <enclosure url="${escapeXml(img)}" type="image/jpeg" />` : ''}
    </item>`;
  });

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
  <channel>
    <title>婕樂纖仙女館 — 專欄文章</title>
    <link>${siteUrl}/articles</link>
    <description>JEROSSE 婕樂纖官方正品授權經銷。保健知識、美容保養、品牌故事、口碑推薦。</description>
    <language>zh-TW</language>
    <lastBuildDate>${new Date().toUTCString()}</lastBuildDate>
    <atom:link href="${siteUrl}/articles/feed.xml" rel="self" type="application/rss+xml" />
${items.join('\n')}
  </channel>
</rss>`;

  return new Response(xml, {
    headers: {
      'Content-Type': 'application/rss+xml; charset=utf-8',
      'Cache-Control': 'public, max-age=3600, s-maxage=3600',
    },
  });
}
