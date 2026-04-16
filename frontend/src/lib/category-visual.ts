/**
 * Shared category visual metadata — emoji + tint color.
 * Used by product category pills (list page) and category cards (homepage)
 * so both surfaces stay visually consistent.
 */

export interface CategoryVisual {
  emoji: string;
  accent: string;    // tailwind-friendly hex for pill active fill
  ringTint: string;  // ring color for inactive pill
}

const DEFAULT: CategoryVisual = { emoji: '🌼', accent: '#9F6B3E', ringTint: '#e7d9cb' };

const MAP: Array<{ match: RegExp; visual: CategoryVisual }> = [
  { match: /體重/, visual: { emoji: '💪', accent: '#d89a4e', ringTint: '#f1d9a0' } },
  { match: /健康活力/, visual: { emoji: '🌿', accent: '#6b9e3c', ringTint: '#cae89e' } },
  { match: /健康維持/, visual: { emoji: '🍃', accent: '#388a7a', ringTint: '#b7dad1' } },
  { match: /美容美體/, visual: { emoji: '🌸', accent: '#d04d7d', ringTint: '#f9c5d4' } },
  { match: /美容保養|保養/, visual: { emoji: '✨', accent: '#E0748C', ringTint: '#f9b3c9' } },
  { match: /健康保健|保健/, visual: { emoji: '🍃', accent: '#4A9D5F', ringTint: '#c4e5c8' } },
];

export function categoryVisual(name: string | null | undefined): CategoryVisual {
  if (!name) return DEFAULT;
  for (const { match, visual } of MAP) {
    if (match.test(name)) return visual;
  }
  return DEFAULT;
}
