/**
 * Estimate reading time from HTML content.
 * Chinese text: ~400 characters per minute (faster than English ~200 words/min).
 */
export function estimateReadingTime(html: string): number {
  const text = html.replace(/<[^>]*>/g, '').replace(/\s+/g, '');
  const chars = text.length;
  return Math.max(1, Math.ceil(chars / 400));
}
