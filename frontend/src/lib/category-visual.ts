/**
 * Shared category visual metadata — icon + tint color.
 * Used by product category pills (list page) and category cards (homepage)
 * so both surfaces stay visually consistent.
 *
 * Each category gets a distinct accent colour so the page isn't monochrome.
 */

export interface CategoryVisual {
  icon: string;      // SiteIcon name identifier
  accent: string;    // pill active fill / text colour
  ringTint: string;  // card background tint
}

const DEFAULT: CategoryVisual = { icon: 'flower', accent: '#9F6B3E', ringTint: '#e7d9cb' };

const MAP: Array<{ match: RegExp; visual: CategoryVisual }> = [
  { match: /健康活力/, visual: { icon: 'leaf', accent: '#4A9D5F', ringTint: '#d4edda' } },
  { match: /健康維持/, visual: { icon: 'leaf-falling', accent: '#2E8B7A', ringTint: '#c5e4de' } },
  { match: /美容美體/, visual: { icon: 'cherry-blossom', accent: '#D04D7D', ringTint: '#fad4e2' } },
  { match: /旅遊/, visual: { icon: 'airplane', accent: '#2980B9', ringTint: '#c9e4f5' } },
  { match: /露營/, visual: { icon: 'tent', accent: '#D4762C', ringTint: '#f5dfc5' } },
];

export function categoryVisual(name: string | null | undefined): CategoryVisual {
  if (!name) return DEFAULT;
  for (const { match, visual } of MAP) {
    if (match.test(name)) return visual;
  }
  return DEFAULT;
}
