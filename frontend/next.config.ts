import type { NextConfig } from "next";
import pkg from "./package.json";

// Bundle analyzer — only loaded when ANALYZE=true so the server can
// use `npm install --omit=dev` without hitting MODULE_NOT_FOUND.
// Enable with: ANALYZE=true npm run build
const withBundleAnalyzer =
  process.env.ANALYZE === "true"
    ? require("@next/bundle-analyzer")({ enabled: true, openAnalyzer: false })
    : (config: NextConfig) => config;

const nextConfig: NextConfig = {
  // Inline the semver version from package.json into the client bundle so
  // the footer always reflects the actual shipped release. Previously CI
  // overrode this with github.sha which is a 40-char hash, unreadable to
  // customers. Bump package.json version → bump footer display.
  env: {
    NEXT_PUBLIC_APP_VERSION: pkg.version,
  },
  // WordPress → Next.js 301 (Next 回 308，對 SEO 等價) migration redirects.
  // 舊 slug 對照表從舊站 MariaDB `awoo_wp.wp_posts` 撈出並 join 後端
  // `products.wp_id`。促銷/組合 post 沒進 Product model，手動對應到最相關商品。
  async redirects() {
    const productSlugMap: Record<string, string> = {
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
      'jerosse-hyaluronic-acid-tablets':
        '水光錠日本Hyabest專利玻尿酸-口服保養推薦',
      // wp_id=1379
      'jerosse-hydration-mask-bandage':
        '水光繃帶面膜繃帶普拉斯頂級修護-補水保濕',
      // wp_id=1383
      'jerosse-cleansing-gel': '婕肌零-J70婕肌零洗卸凝膠-洗卸保養三合一',
      // wp_id=1387
      'jerosse-婕樂纖-雪聚露-雙效導入精華-官方授權正品':
        '雪聚露-雙效導入精華',
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
      // 促銷 / 組合頁面（WP 有但 Product model 沒進 → 對應到主商品）
      // wp_id=1844 髮露促銷
      'jerosse-shampoo-sale': '法樂蓬洗髮露-法樂蓬強健養護豐盈洗髮露',
      // wp_id=1884 小白瓶促銷
      'jerosse-velvet-body-essence-oil': '急救小白瓶-全效賦活絲絨身體精華油',
      // wp_id=2021 益生菌買三送一
      'jerosse-probiotics-buy3get1': '高機能益生菌',
      // wp_id=2023 葉黃素優惠
      'jerosse-葉黃素晶亮凍優惠':
        '金盞花葉黃素晶亮凍葉黃素果凍-蘋果多多風味',
    };

    const productRedirects = Object.entries(productSlugMap).map(
      ([oldSlug, newSlug]) => ({
        source: `/product/${oldSlug}`,
        destination: `/products/${newSlug}`,
        permanent: true,
      }),
    );

    // 新春福袋類走 /bundles 首頁（兩個 bundle slug 都沒保留到新站）
    const bundleRedirects = [
      'jerosse-cny-2026-lucky-bag-bundle',
      'jerosse-cny-burn-firming-vip-plan',
    ].map((slug) => ({
      source: `/product/${slug}`,
      destination: '/bundles',
      permanent: true,
    }));

    return [
      // ── WP query-param 格式 ─────────────────────
      {
        source: '/',
        has: [{ type: 'query', key: 'post_type', value: 'product' }],
        destination: '/products',
        permanent: true,
      },
      {
        source: '/',
        has: [{ type: 'query', key: 'page_id' }],
        destination: '/',
        permanent: true,
      },

      // ── 精準商品對應（最優先，放在 catch-all 前）──
      ...productRedirects,
      ...bundleRedirects,

      // ── 商品 catch-all（對應不到的舊 slug）────────
      { source: '/product/:slug*', destination: '/products', permanent: true },

      // ── 商品分類（英文 slug 直接對應）─────────────
      {
        source: '/product-category/healthy-vitality-series',
        destination: '/products/category/healthy-vitality-series',
        permanent: true,
      },
      {
        source: '/product-category/functional-health-series',
        destination: '/products/category/functional-health-series',
        permanent: true,
      },
      {
        source: '/product-category/body-beauty-series',
        destination: '/products/category/body-beauty-series',
        permanent: true,
      },
      // 其他（未分類 / 旅遊必帶好物 / 露營必備 / 限時優惠活動等）→ /products
      {
        source: '/product-category/:slug*',
        destination: '/products',
        permanent: true,
      },

      // ── Blog / Shop / Tag / Author ───────────────
      { source: '/blog', destination: '/articles', permanent: true },
      { source: '/blog/:slug*', destination: '/articles', permanent: true },
      { source: '/shop', destination: '/products', permanent: true },
      { source: '/shop/:slug*', destination: '/products', permanent: true },
      { source: '/tag/:slug*', destination: '/', permanent: true },
      { source: '/author/:slug*', destination: '/about', permanent: true },
    ];
  },
  async headers() {
    return [
      {
        source: "/(.*)",
        headers: [
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
          {
            key: "Strict-Transport-Security",
            value: "max-age=63072000; includeSubDomains; preload",
          },
        ],
      },
    ];
  },
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "pandora.js-store.com.tw" },
      { protocol: "https", hostname: "**.js-store.com.tw" },
      { protocol: "http", hostname: "localhost", port: "8000" },
      { protocol: "http", hostname: "127.0.0.1", port: "8000" },
    ],
    // Prefer AVIF → WebP → source. Both are materially smaller than
    // JPEG/PNG at equivalent quality, which matters most on mobile 4G.
    formats: ["image/avif", "image/webp"],
    // Cache optimized variants for 1 day (default 60s thrashes on
    // a low-traffic Linode).
    minimumCacheTTL: 60 * 60 * 24,
    // Narrow the variant matrix to sizes our layouts actually request.
    deviceSizes: [360, 480, 640, 768, 1024, 1280, 1536, 1920],
    imageSizes: [64, 96, 128, 192, 256, 384],
    unoptimized: process.env.NODE_ENV === "development",
  },
  allowedDevOrigins: ["http://localhost:8000"],
  // React 19 View Transitions — lets us morph shared elements across
  // route changes (thumbnail → hero). Degrades to normal nav in Safari.
  experimental: {
    viewTransition: true,
    // Inline critical CSS (via critters) so the 22KB Tailwind chunk
    // doesn't render-block LCP. Above-the-fold styles ship inline,
    // the rest loads async.
    optimizeCss: true,
  },
  // Strip console.* except error/warn in production builds.
  compiler: {
    removeConsole: process.env.NODE_ENV === "production"
      ? { exclude: ["error", "warn"] }
      : false,
  },
};

export default withBundleAnalyzer(nextConfig);
