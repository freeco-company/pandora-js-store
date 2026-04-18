import type { Metadata } from 'next';
import './globals.css';
import { CartProvider } from '@/components/CartProvider';
import { ToastProvider } from '@/components/Toast';
import { AuthProvider } from '@/components/AuthProvider';
import ActivationTracker from '@/components/ActivationTracker';
import { CelebrationProvider } from '@/components/Celebration';
import { SerendipityProvider } from '@/components/Serendipity';
import { MobileMenuProvider } from '@/components/MobileMenuContext';
import Header from '@/components/Header';
import Footer from '@/components/Footer';
import CookieConsent from '@/components/CookieConsent';
import Analytics from '@/components/Analytics';
import CustomCursor from '@/components/CustomCursor';
import MobileDrawer from '@/components/MobileDrawer';
import MobileBottomNavWrapper from '@/components/MobileBottomNavWrapper';
import LineFloatingButton from '@/components/LineFloatingButton';
// LenisProvider removed — conflicts with GSAP ScrollTrigger, causes scroll to freeze
// import LenisProvider from '@/components/LenisProvider';

const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://pandora.js-store.com.tw';

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: '婕樂纖仙女館 JEROSSE｜官方正品授權',
    template: '%s｜婕樂纖仙女館',
  },
  description:
    '婕樂纖仙女館 FP — JEROSSE 官方正品授權經銷。保健食品、美容保養、體重管理、亮顏保濕、葉黃素、益生菌、酵素、玻尿酸。1+1 搭配價、滿額 VIP 優惠。',
  keywords: [
    '婕樂纖', 'JEROSSE', 'FP', 'Fairy Pandora',
    '保健食品', '美容保養', '體重管理', '亮顏保濕',
    '葉黃素', '益生菌', '酵素', '玻尿酸', '口服玻尿酸',
    '仙女館', '健康食品', '減脂飲品', '膠原蛋白', '面膜',
    '纖纖飲', '水光錠', '益生菌推薦', '葉黃素推薦',
  ],
  openGraph: {
    type: 'website',
    locale: 'zh_TW',
    siteName: '婕樂纖仙女館',
    url: siteUrl,
  },
  twitter: {
    card: 'summary_large_image',
  },
  alternates: {
    canonical: '/',
  },
  robots: {
    index: true,
    follow: true,
  },
  icons: {
    icon: [{ url: '/favicon.svg', type: 'image/svg+xml' }],
    apple: '/favicon.svg',
  },
  manifest: '/manifest.json',
  appleWebApp: {
    capable: true,
    statusBarStyle: 'black-translucent',
    title: '婕樂纖仙女館',
  },
};

// Derive the storage origin for preconnect (images served from Laravel /storage/)
const storageOrigin = (() => {
  try {
    return new URL(process.env.NEXT_PUBLIC_STORAGE_URL || 'https://pandora.js-store.com.tw').origin;
  } catch {
    return null;
  }
})();

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="zh-TW" className="h-full antialiased">
      <head>
        <meta name="theme-color" content="#e7d9cb" />
        {/* Geo targeting — Taiwan */}
        <meta name="geo.region" content="TW" />
        <meta name="geo.placename" content="Taipei, Taiwan" />
        <meta name="geo.position" content="25.0330;121.5654" />
        <meta name="ICBM" content="25.0330, 121.5654" />
        {/* OpenSearch autodiscovery — lets browsers offer site search in the URL bar */}
        <link rel="search" type="application/opensearchdescription+xml" title="婕樂纖仙女館" href="/opensearch.xml" />
        {/* RSS feed autodiscovery — AI crawlers and aggregators use this */}
        <link rel="alternate" type="application/rss+xml" title="婕樂纖仙女館 — 專欄文章" href="/articles/feed.xml" />
        {/* Preconnect to storage origin for faster LCP on product/article hero images */}
        {storageOrigin && (
          <>
            <link rel="preconnect" href={storageOrigin} crossOrigin="anonymous" />
            <link rel="dns-prefetch" href={storageOrigin} />
          </>
        )}
      </head>
      <body className="min-h-full flex flex-col bg-white text-gray-900">
        {/* GTM noscript fallback — must be right after <body> */}
        {process.env.NEXT_PUBLIC_GTM_ID && (
          <noscript>
            <iframe
              src={`https://www.googletagmanager.com/ns.html?id=${process.env.NEXT_PUBLIC_GTM_ID}`}
              height="0"
              width="0"
              style={{ display: 'none', visibility: 'hidden' }}
            />
          </noscript>
        )}
        <a
          href="#main-content"
          className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:bg-[#9F6B3E] focus:text-white focus:rounded-lg"
        >
          跳到主要內容
        </a>
        <CartProvider>
          <ToastProvider>
            <AuthProvider>
              <CelebrationProvider>
                <SerendipityProvider>
                  <MobileMenuProvider>
                    <CustomCursor />
                    <ActivationTracker />
                    <Header />
                    <main
                      id="main-content"
                      className="flex-1 pt-[64px] md:pt-[80px]"
                    >
                      {children}
                    </main>
                    <Footer />
                    <LineFloatingButton />
                    <CookieConsent />
                    <Analytics />
                    <MobileDrawer />
                    <MobileBottomNavWrapper />
                  </MobileMenuProvider>
                </SerendipityProvider>
              </CelebrationProvider>
            </AuthProvider>
          </ToastProvider>
        </CartProvider>
      </body>
    </html>
  );
}
