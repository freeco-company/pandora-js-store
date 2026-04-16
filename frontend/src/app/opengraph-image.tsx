import { ImageResponse } from 'next/og';

export const runtime = 'edge';
export const alt = '婕樂纖仙女館 JEROSSE';
export const size = { width: 1200, height: 630 };
export const contentType = 'image/png';

export default async function Image() {
  return new ImageResponse(
    (
      <div
        style={{
          background: 'linear-gradient(135deg, #e7d9cb 0%, #f5ede4 100%)',
          width: '100%',
          height: '100%',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          justifyContent: 'center',
          fontFamily: 'sans-serif',
        }}
      >
        <div style={{ fontSize: 72, fontWeight: 700, color: '#9F6B3E', marginBottom: 16 }}>
          婕樂纖仙女館
        </div>
        <div style={{ fontSize: 32, color: '#6b7280' }}>
          JEROSSE 官方正品授權經銷商
        </div>
        <div style={{ fontSize: 24, color: '#9F6B3E', marginTop: 24, opacity: 0.8 }}>
          全館多件優惠 · 滿額享 VIP 價
        </div>
      </div>
    ),
    { ...size }
  );
}
