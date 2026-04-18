import type { Metadata } from 'next';
import AboutPage from './AboutClient';
import { breadcrumbSchema, jsonLdScript } from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '關於 FP｜婕樂纖仙女館團隊',
  description:
    'Fairy Pandora 婕樂纖仙女館 — JEROSSE 官方正品授權經銷。認識創辦人朵朵與我們的團隊。',
  alternates: { canonical: '/about' },
  openGraph: {
    title: '關於 FP｜婕樂纖仙女館團隊',
    description: '認識 Fairy Pandora 團隊，陪你從仙女蛻變成潘朵拉。',
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
