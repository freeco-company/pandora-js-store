/**
 * Structured-data helpers (schema.org JSON-LD).
 * Emitted inline in Server Components via <script type="application/ld+json" />.
 *
 * Reference: https://developers.google.com/search/docs/appearance/structured-data
 */

import { SITE_URL } from './site';

const siteUrl = SITE_URL;

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
      '婕樂纖仙女館（Fairy Pandora, FP）是 JEROSSE 婕樂纖官方正品授權經銷商，由創辦人朵朵帶領 FP 皇家團隊，以「真實分享、不話術」為核心經營理念。提供纖飄錠、纖纖飲X、爆纖錠、益生菌、葉黃素、水光錠、口服玻尿酸等健康保健與美容保養產品，採三階梯定價：單件原價、任選兩件享搭配價、滿 NT$4,000 自動升級 VIP 優惠價。',
    slogan: '由內而外，綻放光彩',
    knowsAbout: [
      'JEROSSE 婕樂纖',
      '保健食品',
      '體重管理',
      '葉黃素',
      '益生菌',
      '口服玻尿酸',
      '膠原蛋白',
      '國家健康食品認證',
      '美容保養',
    ],
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
    founder: { '@id': `${siteUrl}/about#duoduo` },
    address: {
      '@type': 'PostalAddress',
      addressCountry: 'TW',
    },
  };
}

/**
 * LocalBusiness — surfaces in Google Maps / local search / AI overviews.
 * Inherits from Organization for linked-data continuity.
 */
export function localBusinessSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'OnlineStore',
    '@id': `${siteUrl}/#localbusiness`,
    name: '婕樂纖仙女館',
    alternateName: ['JEROSSE 婕樂纖仙女館', 'Fairy Pandora', 'FP'],
    url: siteUrl,
    logo: `${siteUrl}/favicon.svg`,
    description:
      'JEROSSE 婕樂纖官方正品授權經銷商。提供保健食品、美容保養、體重管理產品，三階梯定價讓你買越多省越多。',
    telephone: '+886-978-005-177',
    email: 'care@js-store.com.tw',
    address: {
      '@type': 'PostalAddress',
      addressCountry: 'TW',
    },
    priceRange: 'NT$590–NT$3,980',
    currenciesAccepted: 'TWD',
    paymentAccepted: '信用卡, ATM, 超商代碼',
    areaServed: {
      '@type': 'Country',
      name: 'TW',
    },
    sameAs: [
      'https://www.instagram.com/pandorasdo/',
      'https://lin.ee/62wj7qa',
      'https://pandora.js-store.com.tw',
    ],
    parentOrganization: { '@id': `${siteUrl}/#organization` },
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
 * Uses AggregateOffer when multiple price tiers exist (3-tier pricing).
 */
export function productSchema(product: {
  name: string;
  description: string;
  image?: string | null;
  slug: string;
  price: number;
  comboPrice?: number | null;
  vipPrice?: number | null;
  isActive: boolean;
  stockStatus?: string | null;
  sku?: string | null;
  reviewCount?: number;
  reviewRating?: number;
}) {
  const url = `${siteUrl}/products/${product.slug}`;
  const productId = `${url}#product`;
  const availability = !product.isActive
    ? 'https://schema.org/Discontinued'
    : product.stockStatus === 'outofstock'
      ? 'https://schema.org/OutOfStock'
      : 'https://schema.org/InStock';
  const priceValidUntil = new Date(
    new Date().getFullYear(), 11, 31,
  ).toISOString().split('T')[0];

  // Use AggregateOffer when combo/vip tiers exist
  const hasMultiplePrices = product.comboPrice || product.vipPrice;
  const lowestPrice = Math.min(
    product.price,
    product.comboPrice || product.price,
    product.vipPrice || product.price,
  );

  const offers = hasMultiplePrices
    ? {
        '@type': 'AggregateOffer',
        lowPrice: lowestPrice,
        highPrice: product.price,
        priceCurrency: 'TWD',
        offerCount: [product.price, product.comboPrice, product.vipPrice].filter(Boolean).length,
        availability,
        url,
        offers: [
          {
            '@type': 'Offer',
            name: '單件零售價',
            price: product.price,
            priceCurrency: 'TWD',
            availability,
            priceValidUntil,
          },
          ...(product.comboPrice ? [{
            '@type': 'Offer',
            name: '搭配價（任選 2 件）',
            price: product.comboPrice,
            priceCurrency: 'TWD',
            availability,
            priceValidUntil,
          }] : []),
          ...(product.vipPrice ? [{
            '@type': 'Offer',
            name: 'VIP 價（滿 $4,000）',
            price: product.vipPrice,
            priceCurrency: 'TWD',
            availability,
            priceValidUntil,
          }] : []),
        ],
      }
    : {
        '@type': 'Offer',
        price: product.price,
        priceCurrency: 'TWD',
        availability,
        url,
        seller: { '@id': `${siteUrl}/#organization` },
        priceValidUntil,
      };

  return {
    '@context': 'https://schema.org',
    '@type': 'Product',
    '@id': productId,
    name: product.name,
    description: product.description,
    image: product.image || undefined,
    url,
    brand: { '@type': 'Brand', name: 'JEROSSE 婕樂纖' },
    sku: product.sku || product.slug,
    offers,
    ...(product.reviewCount && product.reviewRating ? {
      aggregateRating: {
        '@type': 'AggregateRating',
        ratingValue: product.reviewRating,
        reviewCount: product.reviewCount,
        bestRating: 5,
        worstRating: 1,
      },
    } : {}),
  };
}

/**
 * AboutPage schema — signals to Google + AI crawlers that this URL is the
 * canonical "about the brand" entry point. Links back to the Organization
 * node so knowledge panels and AI overviews pull consistent facts.
 */
export function aboutPageSchema(opts?: { url?: string; description?: string }) {
  const url = opts?.url ? `${siteUrl}${opts.url}` : `${siteUrl}/about`;
  return {
    '@context': 'https://schema.org',
    '@type': 'AboutPage',
    '@id': `${url}#aboutpage`,
    url,
    name: '關於 FP｜婕樂纖仙女館團隊',
    description:
      opts?.description ||
      'Fairy Pandora (FP) 是 JEROSSE 婕樂纖官方正品授權經銷，由創辦人朵朵帶領 FP 皇家團隊，提供保健食品、美容保養、體重管理產品的官方經銷服務。',
    inLanguage: 'zh-TW',
    mainEntity: { '@id': `${siteUrl}/#organization` },
    isPartOf: { '@id': `${siteUrl}/#website` },
  };
}

/**
 * Founder schema — rich Person node with bio keywords, used on /about.
 * Linked into Organization via `founder` reference, so AI crawlers can
 * attribute the brand story to a real human.
 */
export function founderSchema() {
  return {
    '@context': 'https://schema.org',
    '@type': 'Person',
    '@id': `${siteUrl}/about#duoduo`,
    name: '朵朵',
    alternateName: 'Duoduo',
    jobTitle: 'Co-Founder · FP 皇家團隊長',
    worksFor: { '@id': `${siteUrl}/#organization` },
    description:
      '朵朵是婕樂纖仙女館（Fairy Pandora）的共同創辦人，從 36 歲素人二寶媽起步，兩年時間成為 JEROSSE 最高階皇家團隊長，帶領 FP 團隊以「真實分享」為核心理念經營健康保健電商。',
    knowsAbout: [
      'JEROSSE 婕樂纖',
      '保健食品',
      '體重管理',
      '健康食品認證',
      '女性美容保養',
      '電商經營',
      '社群口碑行銷',
    ],
    nationality: 'TW',
  };
}

/**
 * Per-product FAQ — generic questions derived from product fields.
 *
 * Google requires FAQ schema to match visible content on the page; render
 * these via <ProductFaq /> alongside emitting this schema.
 */
export function productFaqs(product: {
  name: string;
  comboPrice?: number | null;
  vipPrice?: number | null;
  hfCertNo?: string | null;
  hfCertClaim?: string | null;
}): Array<{ question: string; answer: string }> {
  const faqs: Array<{ question: string; answer: string }> = [];

  if (product.hfCertNo && product.hfCertClaim) {
    faqs.push({
      question: `${product.name} 有通過國家健康食品認證嗎？`,
      answer: `本產品已通過衛福部健康食品認證，認證字號 ${product.hfCertNo}。核准功效：${product.hfCertClaim}。`,
    });
  }

  faqs.push({
    question: `${product.name} 怎麼購買最划算？`,
    answer:
      product.comboPrice && product.vipPrice
        ? `婕樂纖仙女館採三階梯定價：單件為原價；任選兩件不同商品享搭配價；整車搭配價小計滿 NT$4,000 自動升級為 VIP 優惠價。整車同享同一階梯，不跨品項混算。`
        : product.comboPrice
          ? `任選兩件不同商品享搭配價，全車自動切換，同品項也可搭配其他品項。`
          : `本商品以單件原價購入，未提供多件搭配折扣。`,
  });

  faqs.push({
    question: '多久會出貨？幾天會收到？',
    answer: '訂單成立且付款完成後，官方出貨倉 24 小時內寄出（週末與國定假日順延）。宅配由物流配送，一般 1–2 個工作天到貨，偏遠地區 2–3 天。超商取貨約 2–3 個工作天到店。',
  });

  faqs.push({
    question: '可以退貨嗎？',
    answer: '依消保法規定，商品送達後 7 日內享有鑑賞期。食品、保健食品、美容開口類商品一經拆封、啟封或使用後，基於衛生安全考量恕不接受退換貨；未拆封商品請保持完整包裝並於 7 日內主動聯繫客服辦理。詳情請參閱「退換貨政策」。',
  });

  faqs.push({
    question: '如何確認是 JEROSSE 官方正品？',
    answer: '婕樂纖仙女館（Fairy Pandora）為 JEROSSE 婕樂纖官方授權經銷商，每件商品皆由原廠統一配送，包裝附有防偽標籤可掃碼驗證。若有任何疑慮，歡迎透過 LINE 客服查詢授權紀錄。',
  });

  return faqs;
}

/**
 * CollectionPage — wraps ItemList for category landing pages so Google
 * understands the URL is a curated collection (not a single product),
 * and can attribute the listed products to this canonical page.
 *
 * Pair with itemList on the same page.
 */
export function collectionPageSchema(opts: {
  url: string;            // absolute or relative
  name: string;
  description?: string;
  numberOfItems?: number;
}) {
  const url = opts.url.startsWith('http') ? opts.url : `${siteUrl}${opts.url}`;
  return {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    '@id': `${url}#collection`,
    url,
    name: opts.name,
    ...(opts.description ? { description: opts.description } : {}),
    inLanguage: 'zh-TW',
    isPartOf: { '@id': `${siteUrl}/#website` },
    ...(opts.numberOfItems !== undefined ? { numberOfItems: opts.numberOfItems } : {}),
  };
}

/**
 * Per-review schema for the top N reviews on a product page.
 * Returns an array — caller spreads into the page's @graph payload.
 *
 * Google requires reviews to match visible content. Emit only what the
 * page actually renders.
 */
export function reviewsSchema(
  productSlug: string,
  reviews: Array<{
    rating: number;
    title?: string | null;
    body: string;
    customer_name?: string | null;
    created_at: string;
  }>,
) {
  const productUrl = `${siteUrl}/products/${productSlug}`;
  return reviews.map((r) => ({
    '@context': 'https://schema.org',
    '@type': 'Review',
    itemReviewed: { '@id': `${productUrl}#product` },
    reviewRating: {
      '@type': 'Rating',
      ratingValue: r.rating,
      bestRating: 5,
      worstRating: 1,
    },
    author: {
      '@type': 'Person',
      name: r.customer_name || '匿名仙女',
    },
    datePublished: r.created_at,
    ...(r.title ? { name: r.title } : {}),
    reviewBody: r.body,
  }));
}

/**
 * Speakable — marks the headline + lead paragraph as Google-Assistant
 * readable for article pages. Improves the chance of TTS surface in
 * Google Assistant / smart speakers, and AI overview citations.
 *
 * The cssSelector list MUST match selectors actually present on the page.
 */
export function speakableSchema(selectors: string[] = ['h1', '[data-speakable]']) {
  return {
    '@context': 'https://schema.org',
    '@type': 'SpeakableSpecification',
    cssSelector: selectors,
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
  wordCount?: number;
}) {
  return {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: article.title,
    description: article.excerpt || article.title,
    image: article.image || undefined,
    datePublished: article.publishedAt,
    ...(article.updatedAt ? { dateModified: article.updatedAt } : { dateModified: article.publishedAt }),
    ...(article.wordCount ? { wordCount: article.wordCount } : {}),
    author: { '@type': 'Organization', name: '婕樂纖仙女館' },
    publisher: {
      '@type': 'Organization',
      name: '婕樂纖仙女館',
      logo: { '@type': 'ImageObject', url: `${siteUrl}/favicon.svg` },
    },
    mainEntityOfPage: { '@type': 'WebPage', '@id': `${siteUrl}/articles/${article.slug}` },
    speakable: {
      '@type': 'SpeakableSpecification',
      cssSelector: ['h1', '[data-speakable]'],
    },
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
