import type { NextConfig } from "next";
import nextBundleAnalyzer from "@next/bundle-analyzer";

// Enable with: ANALYZE=true npm run build
// Outputs HTML reports to .next/analyze/ (open client.html / server.html)
const withBundleAnalyzer = nextBundleAnalyzer({
  enabled: process.env.ANALYZE === "true",
  openAnalyzer: false,
});

const nextConfig: NextConfig = {
  images: {
    remotePatterns: [
      { protocol: "https", hostname: "shop.jerosse.tw" },
      { protocol: "https", hostname: "pandora-dev.js-store.com.tw" },
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
  },
  // Strip console.* except error/warn in production builds.
  compiler: {
    removeConsole: process.env.NODE_ENV === "production"
      ? { exclude: ["error", "warn"] }
      : false,
  },
};

export default withBundleAnalyzer(nextConfig);
