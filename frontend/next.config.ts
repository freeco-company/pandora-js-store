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
    unoptimized: process.env.NODE_ENV === "development",
  },
  allowedDevOrigins: ["http://localhost:8000"],
};

export default withBundleAnalyzer(nextConfig);
