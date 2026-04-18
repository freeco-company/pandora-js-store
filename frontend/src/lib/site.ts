/**
 * Canonical production URL. Hardcoded on purpose — previously read from
 * NEXT_PUBLIC_SITE_URL, whose stale value post-launch leaked into robots.txt,
 * canonical tags, JSON-LD, and preconnect hints.
 */
export const SITE_URL = 'https://pandora.js-store.com.tw';
