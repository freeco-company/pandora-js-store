import type { Metadata } from 'next';
import AboutPage from '@/components/AboutPage';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '關於 FP｜從仙女到潘朵拉的蛻變之旅',
  description: 'Fairy Pandora — 不只是品牌名，是每位女性從認識自己開始，打開專屬美麗盒子的旅程。',
  alternates: { canonical: '/about' },
  openGraph: {
    title: '關於 FP｜從仙女到潘朵拉的蛻變之旅',
    description: 'Fairy Pandora — 不只是品牌名，是每位女性從認識自己開始，打開專屬美麗盒子的旅程。',
  },
};

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';

const teamJsonLd = [{
  '@context': 'https://schema.org', '@type': 'Person',
  '@id': `${siteUrl}/about#duoduo`, name: '朵朵',
  jobTitle: 'Co-Founder · 婕樂纖仙女館',
  worksFor: { '@id': `${siteUrl}/#organization` },
}];

export default function Page() {
  const breadcrumbs = breadcrumbSchema([{ name: '首頁', url: '/' }, { name: '關於 FP' }]);
  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: jsonLdScript(breadcrumbs, ...teamJsonLd) }} />
      <AboutPage />
    </>
  );
}
