/**
 * Canonical production URL. Hardcoded on purpose — previously read from
 * NEXT_PUBLIC_SITE_URL which drifted between pandora-dev and pandora post-launch
 * and leaked into robots.txt, canonical tags, JSON-LD, and preconnect hints.
 */
export const SITE_URL = 'https://pandora.js-store.com.tw';
