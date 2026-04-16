import { fetchApi, type Product, type Article } from '@/lib/api';

export const revalidate = 3600;

export async function GET() {
  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://shop.jerosse.tw';

  let products: Product[] = [];
  let articles: { data: Article[] } = { data: [] };
  try {
    products = await fetchApi<Product[]>('/products');
  } catch {}
  try {
    articles = await fetchApi<{ data: Article[] }>('/articles?per_page=200');
  } catch {}

  const lines = [
    '# 婕樂纖仙女館 JEROSSE — 完整資訊',
    '',
    '> JEROSSE 婕樂纖官方正品授權經銷商。提供健康美麗保健食品，全館多件優惠，滿額享 VIP 價。',
    '',
    '## 品牌資訊',
    '- 品牌名稱：JEROSSE 婕樂纖',
    '- 經銷商名稱：法芮可有限公司（婕樂纖仙女館）',
    '- 服務地區：台灣',
    '- 使用貨幣：TWD（新台幣）',
    '- 網站語言：繁體中文（zh-TW）',
    `- 官方網站：${siteUrl}`,
    '- 聯絡信箱：contact@freeco.cc',
    '',
    '## 價格方案說明',
    '本站採三階優惠制度：',
    '1. **原價**：購買單件商品時適用',
    '2. **1+1 搭配價**：購物車中有 2 件以上不同商品時，所有商品自動適用搭配價',
    '3. **VIP 優惠價**：當搭配價總額達 $4,000 以上，所有商品自動升級為 VIP 價',
    '',
    '## 配送方式',
    '- 宅配到府（免運費）',
    '- 7-11 超商取貨（免運費）',
    '- 全家超商取貨（免運費）',
    '',
    '## 付款方式',
    '- 信用卡付款（綠界金流）',
    '- ATM 轉帳',
    '- 貨到付款（限已註冊會員）',
    '',
    '---',
    '',
    '## 商品目錄',
    '',
    ...products.flatMap((p) => [
      `### ${p.name}`,
      `- 網址：${siteUrl}/products/${p.slug}`,
      `- 原價：$${p.price}`,
      ...(p.combo_price ? [`- 搭配價：$${p.combo_price}`] : []),
      ...(p.vip_price ? [`- VIP 價：$${p.vip_price}`] : []),
      ...(p.short_description ? [`- 簡介：${p.short_description.replace(/\n/g, ' ').slice(0, 200)}`] : []),
      '',
    ]),
    '---',
    '',
    '## 文章列表',
    '',
    ...articles.data.flatMap((a) => {
      const typeLabel = { blog: '婕樂纖誌', news: '媒體報導', brand: '品牌事蹟', recommend: '口碑推薦' }[a.source_type] || a.source_type;
      return [
        `### ${a.title}`,
        `- 網址：${siteUrl}/articles/${a.slug}`,
        `- 分類：${typeLabel}`,
        ...(a.published_at ? [`- 發布日期：${new Date(a.published_at).toISOString().split('T')[0]}`] : []),
        ...(a.excerpt ? [`- 摘要：${a.excerpt.slice(0, 200)}`] : []),
        '',
      ];
    }),
  ];

  return new Response(lines.join('\n'), {
    headers: { 'Content-Type': 'text/plain; charset=utf-8' },
  });
}
