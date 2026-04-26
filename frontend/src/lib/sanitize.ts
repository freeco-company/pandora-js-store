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
