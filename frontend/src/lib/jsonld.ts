/**
 * Structured-data helpers (schema.org JSON-LD).
 * Emitted inline in Server Components via <script type="application/ld+json" />.
 *
 * Reference: https://developers.google.com/search/docs/appearance/structured-data
 */

const siteUrl =
  process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';

/**
 * Organization — Google knowledge panel + AI search (Perplexity/Google AI Overview).
 * Include logo, social links, contact point for maximum coverage.
 */
export function organizationSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    '@id': `${siteUrl}/#organization`,
    name: '婕樂纖仙女館',
    alternateName: ['JEROSSE 婕樂纖', 'Fairy Pandora', 'FP'],
    url: siteUrl,
    logo: {
      '@type': 'ImageObject',
      url: `${siteUrl}/favicon.svg`,
      width: 512,
      height: 512,
    },
    description:
      'JEROSSE 婕樂纖官方正品授權經銷商。健康保健食品、美容保養、體重管理、葉黃素、益生菌、口服玻尿酸。1+1 搭配價、滿額 VIP 優惠。',
    sameAs: [
      'https://www.instagram.com/pandorasdo/',
      'https://lin.ee/62wj7qa',
    ],
    contactPoint: {
      '@type': 'ContactPoint',
      contactType: 'customer service',
      availableLanguage: ['zh-TW', 'zh-Hant'],
      areaServed: 'TW',
      url: 'https://lin.ee/62wj7qa',
    },
    foundingDate: '2020',
    founder: {
      '@type': 'Person',
      name: '朵朵',
    },
    address: {
      '@type': 'PostalAddress',
      addressCountry: 'TW',
    },
  };
}

/**
 * WebSite with SearchAction — gives Google result a Sitelinks search box
 * pointing at our internal /products?q= handler.
 */
export function websiteSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    '@id': `${siteUrl}/#website`,
    url: siteUrl,
    name: '婕樂纖仙女館',
    description: 'JEROSSE 婕樂纖官方正品授權經銷',
    publisher: { '@id': `${siteUrl}/#organization` },
    inLanguage: 'zh-TW',
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${siteUrl}/products?q={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  };
}

/**
 * BreadcrumbList — shows search-result path "首頁 › 商品 › 產品名".
 * Pass an ordered list of {name, url}. The last item should be the current page.
 */
export function breadcrumbSchema(items: Array<{ name: string; url?: string }>) {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: item.name,
      ...(item.url ? { item: item.url.startsWith('http') ? item.url : `${siteUrl}${item.url}` } : {}),
    })),
  };
}

/**
 * FAQPage — for static Q&A pages. Produces "People also ask" style rich result.
 */
export function faqSchema(faqs: Array<{ question: string; answer: string }>) {
  return {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map((f) => ({
      '@type': 'Question',
      name: f.question,
      acceptedAnswer: {
        '@type': 'Answer',
        text: f.answer,
      },
    })),
  };
}

/**
 * HowTo — "How to save with 3-tier pricing" for homepage.
 * Shows step-by-step in search results.
 */
export function howToSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'HowTo',
    name: '如何在婕樂纖仙女館享受最優惠價格',
    description: '透過三階梯定價機制，買越多省越多。任選 2 件享搭配價，滿 NT$4,000 自動升級 VIP 優惠價。',
    step: [
      {
        '@type': 'HowToStep',
        name: '選擇第一件商品',
        text: '瀏覽全館商品，選擇一件加入購物車，以原價計算。',
        url: `${siteUrl}/products`,
      },
      {
        '@type': 'HowToStep',
        name: '任選第二件，解鎖搭配價',
        text: '再選一件不同或相同商品，全車自動切換為 1+1 搭配價，每件立即省下差額。',
        url: `${siteUrl}/products`,
      },
      {
        '@type': 'HowToStep',
        name: '滿 $4,000 升級 VIP 價',
        text: '搭配價小計達 NT$4,000，全車再自動升級為 VIP 優惠價，享受最低價格。',
        url: `${siteUrl}/cart`,
      },
    ],
  };
}

/**
 * Product schema helper — includes seller, sku, brand for Google Merchant
 * Center and Shopping rich results.
 */
export function productSchema(product: {
  name: string;
  description: string;
  image?: string | null;
  slug: string;
  price: number;
  isActive: boolean;
  sku?: string | null;
}) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description,
    image: product.image || undefined,
    url: `${siteUrl}/products/${product.slug}`,
    brand: { '@type': 'Brand', name: 'JEROSSE 婕樂纖' },
    sku: product.sku || product.slug,
    offers: {
      '@type': 'Offer',
      price: product.price,
      priceCurrency: 'TWD',
      availability: product.isActive
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      url: `${siteUrl}/products/${product.slug}`,
      seller: { '@id': `${siteUrl}/#organization` },
      priceValidUntil: new Date(
        new Date().getFullYear(),
        11,
        31,
      ).toISOString().split('T')[0],
    },
  };
}

/**
 * Article schema helper — includes dateModified for freshness signal.
 */
export function articleSchema(article: {
  title: string;
  excerpt?: string;
  image?: string | null;
  slug: string;
  publishedAt: string;
  updatedAt?: string | null;
}) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: article.title,
    description: article.excerpt || article.title,
    image: article.image || undefined,
    datePublished: article.publishedAt,
    ...(article.updatedAt ? { dateModified: article.updatedAt } : { dateModified: article.publishedAt }),
    author: { '@type': 'Organization', name: '婕樂纖仙女館' },
    publisher: {
      '@type': 'Organization',
      name: '婕樂纖仙女館',
      logo: { '@type': 'ImageObject', url: `${siteUrl}/favicon.svg` },
    },
    mainEntityOfPage: { '@type': 'WebPage', '@id': `${siteUrl}/articles/${article.slug}` },
  };
}

/**
 * Serialize one or more schemas into a single <script> payload.
 * Uses @graph wrapper for multi-schema payloads for cleaner linked data.
 * React renders this via dangerouslySetInnerHTML.
 */
export function jsonLdScript(...schemas: object[]): string {
  if (schemas.length === 1) return JSON.stringify(schemas[0]);
  // Strip individual @context from each schema and wrap in a single @graph
  const stripped = schemas.map((s) => {
    const { '@context': _, ...rest } = s as Record<string, unknown>;
    return rest;
  });
  return JSON.stringify({ '@context': 'https://schema.org', '@graph': stripped });
}
