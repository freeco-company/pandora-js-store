/**
 * Structured-data helpers (schema.org JSON-LD).
 * Emitted inline in Server Components via <script type="application/ld+json" />.
 *
 * Reference: https://developers.google.com/search/docs/appearance/structured-data
 */

const siteUrl =
  process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora-dev.js-store.com.tw';

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
      'https://lin.ee/pandorasdo',
    ],
    contactPoint: {
      '@type': 'ContactPoint',
      contactType: 'customer service',
      availableLanguage: ['zh-TW', 'zh-Hant'],
      areaServed: 'TW',
      url: 'https://lin.ee/pandorasdo',
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
 * Serialize one or more schemas into a single <script> payload.
 * React renders this via dangerouslySetInnerHTML.
 */
export function jsonLdScript(...schemas: object[]): string {
  if (schemas.length === 1) return JSON.stringify(schemas[0]);
  return JSON.stringify(schemas);
}
