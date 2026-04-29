/**
 * Map an achievement code + tier → @freeco-company/pandora-design-svg
 * tier badge filename. Theme is derived from the code prefix:
 *   first_* → first
 *   streak_* → streak
 *   anything else (spend / order / vip / referral / unlock / explore) → milestone
 *
 * The actual SVG files live at /svg/badges/badge_{theme}_{tier}.svg in
 * public/, synced from the npm package via `prebuild` script.
 */

import type { AchievementTier } from './achievements';

export type BadgeTheme = 'first' | 'streak' | 'milestone';

export function badgeTheme(code: string): BadgeTheme {
  if (code.startsWith('first_')) return 'first';
  if (code.startsWith('streak_')) return 'streak';
  return 'milestone';
}

export function badgeUrl(code: string, tier: AchievementTier): string {
  return `/svg/badges/badge_${badgeTheme(code)}_${tier}.svg`;
}
