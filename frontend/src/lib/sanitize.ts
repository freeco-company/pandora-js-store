import DOMPurify from 'isomorphic-dompurify';

const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div', 'iframe', 'hr'];
const ALLOWED_ATTR = ['href', 'src', 'alt', 'class', 'style', 'target', 'rel', 'width', 'height', 'title'];

/**
 * Sanitize HTML content.
 * Server-side: use a lightweight regex strip (jsdom/DOMPurify can corrupt
 * multi-byte UTF-8 in large strings). Client-side: use DOMPurify.
 */
export function sanitizeHtml(html: string): string {
  if (typeof window === 'undefined') {
    // Server-side: lightweight sanitization
    // Content comes from our own DB (already cleaned by artisan command)
    // Just strip script/event handlers for safety
    let clean = html;
    clean = clean.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
    clean = clean.replace(/\son\w+="[^"]*"/gi, '');
    clean = clean.replace(/\son\w+='[^']*'/gi, '');
    clean = clean.replace(/javascript:/gi, '');
    // Strip Elementor icon SVGs scraped from jerosse.com.tw — we don't load
    // Elementor CSS, so they render unsized and blow up to container width.
    // Client-side DOMPurify already drops all <svg> via ALLOWED_TAGS, but the
    // SSR pass goes through this regex path and was leaking them.
    clean = clean.replace(
      /<svg\b[^>]*\bclass="[^"]*\b(?:e-font-icon-svg|e-eicon|elementor-toc__spinner)\b[^"]*"[^>]*>[\s\S]*?<\/svg>/gi,
      ''
    );
    return clean;
  }

  // Client-side: full DOMPurify
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
    ALLOW_DATA_ATTR: false,
  });
}

/**
 * Strip the leading image-only paragraph from article content.
 * WP-imported articles often start with a <p> wrapping just the cover <img>
 * (plus <noscript> fallback), which duplicates the featured_image we render
 * separately. Only removes when the first block is essentially image-only.
 */
export function stripLeadingCoverImage(html: string): string {
  const trimmed = html.replace(/^\s+/, '');
  // Match leading <p>...</p> that contains an <img> and no real text content.
  const m = trimmed.match(/^<p\b[^>]*>([\s\S]*?)<\/p>/i);
  if (!m) return html;
  const inner = m[1];
  if (!/<img\b/i.test(inner)) return html;
  // Strip tags and whitespace; if anything text-like remains, keep the <p>.
  const text = inner.replace(/<[^>]+>/g, '').replace(/&nbsp;/gi, '').trim();
  if (text.length > 0) return html;
  return trimmed.slice(m[0].length);
}
