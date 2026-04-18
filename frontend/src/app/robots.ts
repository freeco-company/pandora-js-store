import type { MetadataRoute } from 'next';

// Hardcoded: the canonical production URL doesn't change. Reading from env
// caused a post-launch regression where stale pandora.* in PM2's
// process env leaked into the Sitemap declaration served to Google.
const siteUrl = 'https://pandora.js-store.com.tw';

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: '*',
        allow: '/',
        disallow: [
          '/cart',
          '/checkout',
          '/order-complete',
          '/order-lookup',
          '/admin',
          '/api/',
          '/account',
          '/auth/',
          '/login',
          '/register',
        ],
      },
      // Explicitly allow AI search crawlers (opt-in for GEO visibility)
      { userAgent: 'GPTBot', allow: '/' },
      { userAgent: 'Google-Extended', allow: '/' },
      { userAgent: 'PerplexityBot', allow: '/' },
      { userAgent: 'ClaudeBot', allow: '/' },
      { userAgent: 'Applebot-Extended', allow: '/' },
    ],
    sitemap: [
      `${siteUrl}/sitemap.xml`,
      `${siteUrl}/sitemap-images.xml`,
    ],
  };
}
