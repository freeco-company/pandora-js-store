import { ImageResponse } from 'next/og';
import { getProduct } from '@/lib/api';

export const runtime = 'edge';
export const alt = 'JEROSSE 婕樂纖';
export const size = { width: 1200, height: 630 };
export const contentType = 'image/png';

export default async function Image({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  let productName = 'JEROSSE 婕樂纖';
  let price = '';
  try {
    const product = await getProduct(slug);
    productName = product.name;
    price = `$${Math.round(product.price).toLocaleString()}`;
  } catch {}

  return new ImageResponse(
    (
      <div style={{
        background: 'linear-gradient(135deg, #e7d9cb 0%, #f5ede4 100%)',
        width: '100%', height: '100%',
        display: 'flex', flexDirection: 'column',
        alignItems: 'center', justifyContent: 'center',
        fontFamily: 'sans-serif', padding: 60,
      }}>
        <div style={{ fontSize: 28, color: '#9F6B3E', marginBottom: 20, opacity: 0.7 }}>
          婕樂纖仙女館
        </div>
        <div style={{ fontSize: 48, fontWeight: 700, color: '#1f2937', textAlign: 'center', maxWidth: 900, lineHeight: 1.3 }}>
          {productName}
        </div>
        {price && (
          <div style={{ fontSize: 36, color: '#9F6B3E', marginTop: 24, fontWeight: 600 }}>
            {price}
          </div>
        )}
      </div>
    ),
    { ...size }
  );
}
