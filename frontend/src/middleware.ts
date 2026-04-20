import { NextRequest, NextResponse } from 'next/server';

/**
 * WP → Next.js 舊 URL 308 redirect。
 *
 * 為什麼不放 next.config.ts 的 redirects()？path-to-regexp 不會 decode
 * percent-encoded pathname，config 裡用中文 source 寫的精準規則無法命中
 * Google 實際丟過來的 `/product/%E5%A9%95%E6%A8%82%E7%BA%96-...` 請求。
 * middleware 可以先 decodeURIComponent 再比對 Map，完全避開這問題。
 *
 * Map key = 舊 WP `wp_posts.post_name`（decoded 形式）
 * Map value = 新站 product slug（/products/ 後面那段）
 * 來源：ssh freeco awoo_wp.wp_posts × backend products.wp_id
 */
const PRODUCT_SLUG_MAP: Record<string, string> = {
  // wp_id=55
  'jerosse-婕樂纖-雪花紫纖飲-14份-盒':
    '雪花紫纖飲-蔓越莓風味漂浮微泡飲-微氣泡莓果飲-喝水神器',
  // wp_id=427
  '【郭雪芙代言推薦】-我的秘密-纖飄錠-60錠-盒':
    '纖飄錠-郭雪芙代言健康食品認證',
  // wp_id=1348
  'jerosse-婕樂纖-纖纖輕鬆飲x-14包-盒': '纖纖飲X-纖纖輕鬆飲X',
  // wp_id=1354
  'jerosse-婕樂纖-爆纖錠-120顆-瓶': '爆纖錠-小粉',
  // wp_id=1358
  '婕樂纖輕卡肽纖飲-官方授權正品-10包-盒': '輕卡肽纖飲-肽可可',
  // wp_id=1362
  '婕樂纖輕卡肽纖飲-厚焙奶茶-官方授權正品-10包-盒':
    '厚焙奶茶-肽纖飲奶茶口味-仙女奶茶-肽奶茶',
  // wp_id=1366
  'jerosse-婕樂纖-植萃纖酵宿-60錠-盒-官方授權': '植萃纖酵宿',
  // wp_id=1372
  'jerosse-hyaluronic-acid-tablets': '水光錠日本Hyabest專利玻尿酸-口服保養推薦',
  // wp_id=1379
  'jerosse-hydration-mask-bandage': '水光繃帶面膜繃帶普拉斯頂級修護-補水保濕',
  // wp_id=1383
  'jerosse-cleansing-gel': '婕肌零-J70婕肌零洗卸凝膠-洗卸保養三合一',
  // wp_id=1387
  'jerosse-婕樂纖-雪聚露-雙效導入精華-官方授權正品': '雪聚露-雙效導入精華',
  // wp_id=1391
  '婕樂纖-急救小白瓶-全效賦活絲絨身體精華油-100ml-瓶':
    '急救小白瓶-全效賦活絲絨身體精華油',
  // wp_id=1395
  'jerosse-婕樂纖-法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露':
    '法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露',
  // wp_id=1399
  'jerosse-婕樂纖-法樂蓬養髮原液-法樂蓬強健活化養髮原液':
    '法樂蓬養髮原液-法樂蓬強健活化養髮原液',
  // wp_id=1403
  'jerosse-probiotics-official': '高機能益生菌',
  // wp_id=1411
  'jerosse-婕樂纖-金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多':
    '金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多風味',
  // wp_id=1745
  'jerosse-婕樂纖-積雪草護手霜50ml-條-官方授權正品': '積雪草護手霜',
  // wp_id=1752
  'jerosse-婕樂纖-固樂纖dkkflex-60錠-盒-官方授權正品': '固樂纖DKKflex',
  // wp_id=1756
  'jerosse-婕樂纖-療肺草正冠茶20包-盒-官方授權正品': '療肺草正冠茶',
  // wp_id=1760
  'jerosse-婕樂纖-9國英雄turbo極速錠-20顆-包-官方授權正品':
    '9國英雄TURBO極速錠',
  // 促銷 / 組合（WP 有但 Product model 沒進，對應到主商品）
  'jerosse-shampoo-sale': '法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露',
  'jerosse-velvet-body-essence-oil': '急救小白瓶-全效賦活絲絨身體精華油',
  'jerosse-probiotics-buy3get1': '高機能益生菌',
  'jerosse-葉黃素晶亮凍優惠': '金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多風味',
};

// 新春福袋 bundle post → /bundles（沒有對應單品）
const BUNDLE_REDIRECTS = new Set([
  'jerosse-cny-2026-lucky-bag-bundle',
  'jerosse-cny-burn-firming-vip-plan',
]);

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // 只處理 /product/xxx（WP 單品頁）。/products/xxx 是新站不碰。
  if (!pathname.startsWith('/product/')) {
    return NextResponse.next();
  }

  // 去掉 /product/ 前綴 + 尾斜線
  const rawSlug = pathname.slice('/product/'.length).replace(/\/+$/, '');
  if (!rawSlug) {
    return NextResponse.redirect(new URL('/products', request.url), 308);
  }

  let decoded: string;
  try {
    decoded = decodeURIComponent(rawSlug);
  } catch {
    decoded = rawSlug;
  }

  if (decoded in PRODUCT_SLUG_MAP) {
    const newSlug = PRODUCT_SLUG_MAP[decoded];
    return NextResponse.redirect(
      new URL(`/products/${encodeURIComponent(newSlug)}`, request.url),
      308,
    );
  }

  if (BUNDLE_REDIRECTS.has(decoded)) {
    return NextResponse.redirect(new URL('/bundles', request.url), 308);
  }

  // 對應不到 → fallback /products
  return NextResponse.redirect(new URL('/products', request.url), 308);
}

export const config = {
  matcher: ['/product/:path*'],
};
