import { fetchApi, type Product } from '@/lib/api';

export const revalidate = 3600;

export async function GET() {
  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://shop.jerosse.tw';

  let products: Product[] = [];
  try {
    products = await fetchApi<Product[]>('/products');
  } catch {}

  const lines = [
    '# 婕樂纖仙女館 JEROSSE',
    '',
    '> JEROSSE 婕樂纖官方正品授權經銷商。提供健康美麗保健食品，全館多件優惠，滿額享 VIP 價。',
    '',
    '## 品牌資訊',
    '- 品牌：JEROSSE 婕樂纖',
    '- 經銷商：法芮可有限公司（婕樂纖仙女館）',
    '- 地區：台灣',
    '- 幣別：TWD（新台幣）',
    '- 語言：繁體中文',
    '',
    '## 價格方案',
    '- 原價：單件購買',
    '- 1+1 搭配價：任選兩件不同商品即享優惠',
    '- VIP 優惠價：搭配價滿 $4,000 自動升級',
    '',
    '## 頁面',
    `- [首頁](${siteUrl}/)`,
    `- [全館商品](${siteUrl}/products)`,
    `- [最新文章](${siteUrl}/articles)`,
    `- [關於我們](${siteUrl}/about)`,
    `- [加入 FP](${siteUrl}/join)`,
    `- [FP 團隊](${siteUrl}/team)`,
    `- [常見問題](${siteUrl}/faq)`,
    `- [退換貨政策](${siteUrl}/return-policy)`,
    `- [隱私權政策](${siteUrl}/privacy)`,
    `- [完整版 llms-full.txt](${siteUrl}/llms-full.txt)`,
    '',
    '## 商品列表',
    ...products.map((p) =>
      `- [${p.name}](${siteUrl}/products/${p.slug}): 原價 $${p.price}${p.combo_price ? ` / 搭配價 $${p.combo_price}` : ''}${p.vip_price ? ` / VIP $${p.vip_price}` : ''}`
    ),
  ];

  return new Response(lines.join('\n'), {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
}
