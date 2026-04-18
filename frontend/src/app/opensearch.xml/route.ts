import { SITE_URL } from '@/lib/site';
const siteUrl = SITE_URL;

export function GET() {
  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">
  <ShortName>婕樂纖仙女館</ShortName>
  <Description>搜尋 JEROSSE 婕樂纖仙女館商品</Description>
  <InputEncoding>UTF-8</InputEncoding>
  <Image width="16" height="16" type="image/svg+xml">${siteUrl}/favicon.svg</Image>
  <Url type="text/html" template="${siteUrl}/products?q={searchTerms}" />
</OpenSearchDescription>`;

  return new Response(xml, {
    headers: {
      'Content-Type': 'application/opensearchdescription+xml; charset=utf-8',
      'Cache-Control': 'public, max-age=86400',
    },
  });
}
