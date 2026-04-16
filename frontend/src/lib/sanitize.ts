import DOMPurify from 'isomorphic-dompurify';

export function sanitizeHtml(html: string): string {
  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'img', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div', 'iframe'],
    ALLOWED_ATTR: ['href', 'src', 'alt', 'class', 'style', 'target', 'rel', 'width', 'height', 'title'],
    ALLOW_DATA_ATTR: false,
  });
}
