/**
 * Format a number as TWD currency with thousand separators.
 * formatPrice(1280) → "$1,280"
 * formatPrice(1280.5) → "$1,281" (rounded)
 * formatPrice(null) → null
 */
export function formatPrice(price: number | null | undefined): string | null {
  if (price == null) return null;
  return `$${Math.round(price).toLocaleString('zh-TW')}`;
}
