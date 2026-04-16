<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>{{ $product->name }} 已到貨</title>
</head>
<body style="font-family: 'Microsoft JhengHei', '微軟正黑體', sans-serif; background: #f7eee3; margin: 0; padding: 24px;">
  <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #9F6B3E, #85572F); color: #fff; padding: 28px 24px; text-align: center;">
      <div style="font-size: 12px; letter-spacing: 2px; color: #fcd561;">BACK IN STOCK · 到貨通知</div>
      <h1 style="margin: 10px 0 0; font-size: 20px;">你想等的商品回來了 🎉</h1>
    </div>
    <div style="padding: 24px;">
      <h2 style="font-size: 18px; color: #1f1a15; margin: 0 0 8px;">{{ $product->name }}</h2>
      <p style="color: #7a5836; font-size: 14px; line-height: 1.7; margin: 0 0 16px;">
        {{ $product->short_description ?: '商品現已補貨，點下方連結立即選購。' }}
      </p>
      <div style="text-align: center; padding: 12px 0 8px;">
        <a href="{{ config('app.url') }}/products/{{ $product->slug }}"
           style="display: inline-block; padding: 12px 32px; background: #9F6B3E; color: #fff; text-decoration: none; border-radius: 999px; font-weight: bold;">
          立即選購 →
        </a>
      </div>
      <p style="color: #b09070; font-size: 12px; text-align: center; margin: 16px 0 0;">
        庫存有限，建議盡早下單。
      </p>
    </div>
    <div style="background: #fdf7ef; padding: 16px 24px; color: #b09070; font-size: 11px; text-align: center;">
      婕樂纖仙女館 JEROSSE · 官方正品授權經銷
    </div>
  </div>
</body>
</html>
