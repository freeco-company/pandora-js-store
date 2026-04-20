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
  // WordPress → Next.js 301 migration redirects. Chinese-slug 精準對應改走
  // middleware.ts（path-to-regexp 不會 decode percent-encoded path，config
  // 裡寫中文 source 無法命中）。這裡只留純 ASCII 可表達的規則。
  async redirects() {
    return [
      // WP query-param
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
      // 分類（英文）
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
      {
        source: '/product-category/:slug*',
        destination: '/products',
        permanent: true,
      },
      // Blog / Shop / Tag / Author
      { source: '/blog', destination: '/articles', permanent: true },
      { source: '/blog/:slug*', destination: '/articles', permanent: true },
      { source: '/shop', destination: '/products', permanent: true },
      { source: '/shop/:slug*', destination: '/products', permanent: true },
      { source: '/tag/:slug*', destination: '/', permanent: true },
      { source: '/author/:slug*', destination: '/about', permanent: true },
      // 注意：/product/:slug* catch-all 也在 middleware.ts 處理，才能讓精準
      // 對應先命中。
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
