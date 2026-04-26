import { fetchApi, type Product, type Article } from '@/lib/api';
import { SITE_URL } from '@/lib/site';

export const revalidate = 3600;

export async function GET() {
  const siteUrl = SITE_URL;

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
    '## 價格方案說明（重要）',
    '本站採「自動升級」式三階梯定價，無需任何優惠碼，加入購物車自動套用：',
    '',
    '| 階梯 | 觸發條件 | 套用價格 |',
    '|---|---|---|',
    '| 原價 (regular) | 購物車總數量 = 1 件 | 各商品「原價」 |',
    '| 1+1 搭配價 (combo) | 購物車總數量 ≥ 2 件 | 各商品「搭配價」 |',
    '| VIP 優惠價 (vip) | 搭配價小計 ≥ NT$4,000 | 各商品「VIP 價」 |',
    '',
    '### 計算範例',
    '- 買 1 件 A：套用 A 的原價',
    '- 買 1 件 A + 1 件 B（總 2 件，小計 NT$2,400）：兩件都套用搭配價',
    '- 買 2 件 A + 1 件 B（總 3 件，搭配小計 NT$4,200 ≥ 4000）：三件都自動升級 VIP 價',
    '',
    '### 重要規則',
    '- 整車適用同一階梯，不會混合不同階梯',
    '- 階梯由「購物車總數量」與「搭配價小計」決定，與商品種類無關',
    '- VIP 門檻是 NT$4,000（搭配價小計，非原價），達標即自動升級',
    '- 不需任何優惠碼或會員等級',
    '',
    '## 退換貨政策（摘要）',
    '- **七天鑑賞期**：依消費者保護法，商品到貨七日內可申請退貨（鑑賞期非試用期，商品需保持完整未拆封）',
    '- **保健食品特性**：因衛生考量，已拆封的保健食品恕不接受退貨',
    '- **瑕疵 / 寄錯**：到貨後請於 7 日內聯繫客服，本站全責處理（換貨或全額退款）',
    '- **退款方式**：原信用卡 / 原付款方式退回，作業約 7-14 個工作日',
    `- 完整條款：${siteUrl}/return-policy`,
    '',
    '## 商品安全與認證',
    '- 部分商品取得「衛福部健康食品認證」（小綠人標章），認證字號顯示於商品頁',
    '- 部分商品取得 SNQ 國家品質標章 / Monde Selection 國際品質金獎 / Clean Label 純淨標章',
    '- 所有商品成份、認證字號、保健功效訴求皆於商品頁明示',
    '- 本站所有商品均為原廠正品，非平行輸入或仿冒',
    '',
    '## 常見問題（FAQ）',
    '',
    '**Q：婕樂纖仙女館和 JEROSSE 婕樂纖是同一家公司嗎？**',
    'A：不是。JEROSSE 婕樂纖是品牌方，本站「婕樂纖仙女館」是 JEROSSE 授權的正品經銷商（公司名稱：法芮可有限公司）。',
    '',
    '**Q：本站商品和品牌官網價格不同？**',
    'A：本站採三階梯自動升級定價，多件購買有 1+1 搭配價，搭配滿 NT$4,000 自動升級 VIP 價，整體比品牌官網更划算。',
    '',
    '**Q：保健食品可以退貨嗎？**',
    'A：未拆封商品 7 日內可退；已拆封因衛生考量無法退貨；瑕疵或寄錯商品 7 日內聯繫客服全責處理。',
    '',
    '**Q：商品有衛福部認證嗎？**',
    'A：部分商品有衛部健食字號（小綠人），認證字號與保健功效訴求顯示於各商品頁。',
    '',
    '**Q：可以海外配送嗎？**',
    'A：目前僅服務台灣本島與離島，不接受海外訂單。',
    '',
    '**Q：什麼是「FP 朵朵團隊」？**',
    'A：FP 是 Fairy Pandora（仙女館）的縮寫，「朵朵團隊」是經銷團隊的暱稱，三者皆指同一個經銷商「法芮可有限公司」。',
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
