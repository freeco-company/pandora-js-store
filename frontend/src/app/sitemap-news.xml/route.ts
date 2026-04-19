import { fetchApi, type Article } from '@/lib/api';
import { SITE_URL } from '@/lib/site';

/**
 * Google News sitemap.
 *
 * Per Google's spec: only include articles published within the last 48 hours.
 * Re-revalidate frequently so the freshness window stays accurate without
 * requiring a deploy each hour.
 *
 * Reference: https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap
 */
export const revalidate = 600; // 10 min — well under the 48h freshness window

const PUBLICATION_NAME = '婕樂纖仙女館 — 仙女誌';
const PUBLICATION_LANG = 'zh-tw';

export async function GET() {
  const baseUrl = SITE_URL;
  const cutoff = Date.now() - 48 * 60 * 60 * 1000;

  let articles: Article[] = [];
  try {
    // Newest-first: pull a few pages then trim by date. API may not support
    // a since=… filter, so we paginate small and stop once dates fall below cutoff.
    for (let page = 1; page <= 3; page++) {
      const res = await fetchApi<{ data: Article[] }>(`/articles?per_page=100&page=${page}`);
      if (!res.data?.length) break;
      articles.push(...res.data);
      const oldestOnPage = res.data[res.data.length - 1]?.published_at;
      if (oldestOnPage && new Date(oldestOnPage).getTime() < cutoff) break;
    }
  } catch {
    articles = [];
  }

  const recent = articles.filter((a) => {
    if (!a.published_at) return false;
    return new Date(a.published_at).getTime() >= cutoff;
  });

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
${recent
  .map((a) => `  <url>
    <loc>${escape(`${baseUrl}/articles/${a.slug}`)}</loc>
    <news:news>
      <news:publication>
        <news:name>${escape(PUBLICATION_NAME)}</news:name>
        <news:language>${PUBLICATION_LANG}</news:language>
      </news:publication>
      <news:publication_date>${escape(new Date(a.published_at!).toISOString())}</news:publication_date>
      <news:title>${escape(a.title)}</news:title>
    </news:news>
  </url>`)
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
