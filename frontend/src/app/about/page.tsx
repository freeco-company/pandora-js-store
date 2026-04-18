import type { Metadata } from 'next';
import AboutPage from './AboutClient';
import {
  aboutPageSchema,
  breadcrumbSchema,
  founderSchema,
  jsonLdScript,
  organizationSchema,
} from '@/lib/jsonld';

export const revalidate = 86400;

export const metadata: Metadata = {
  title: '關於 FP｜婕樂纖仙女館團隊',
  description:
    'Fairy Pandora (FP) 是 JEROSSE 婕樂纖官方正品授權經銷，由創辦人朵朵帶領皇家團隊經營。以「真實分享、不話術」為核心，陪伴每位仙女找回自信。',
  alternates: { canonical: '/about' },
  openGraph: {
    title: '關於 FP｜婕樂纖仙女館團隊',
    description: 'Fairy Pandora — JEROSSE 官方正品授權。認識創辦人朵朵與皇家團隊。',
  },
};

export default function Page() {
  return (
    <>
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{
          __html: jsonLdScript(
            aboutPageSchema(),
            organizationSchema(),
            founderSchema(),
            breadcrumbSchema([{ name: '首頁', url: '/' }, { name: '關於 FP' }]),
          ),
        }}
      />
      <AboutPage />
    </>
  );
}
