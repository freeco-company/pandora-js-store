<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>訂單 {{ $order->order_number }} 尚未完成</title>
</head>
<body style="font-family: 'Microsoft JhengHei', '微軟正黑體', sans-serif; background: #f7eee3; margin: 0; padding: 24px;">
  <div style="max-width: 520px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #9F6B3E, #85572F); color: #fff; padding: 28px 24px; text-align: center;">
      <div style="font-size: 12px; letter-spacing: 2px; color: #fcd561;">REMINDER · 訂單尚未付款</div>
      <h1 style="margin: 10px 0 0; font-size: 20px;">還差一步就完成囉 💫</h1>
    </div>
    <div style="padding: 24px;">
      <p style="color: #7a5836; font-size: 14px; line-height: 1.7;">
        您在 {{ $order->created_at->format('Y/m/d H:i') }} 建立了訂單
        <strong>#{{ $order->order_number }}</strong>，目前還未完成付款。
      </p>
      <div style="background: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 12px; padding: 16px; margin: 16px 0;">
        <div style="font-size: 12px; color: #b09070; margin-bottom: 8px;">訂單內容</div>
        @foreach ($order->items as $item)
          <div style="font-size: 13px; color: #1f1a15; padding: 2px 0;">• {{ $item->product_name }} ×{{ $item->quantity }}</div>
        @endforeach
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed #e7d9cb; text-align: right;">
          <span style="font-size: 12px; color: #7a5836;">應付金額</span>
          <strong style="color: #9F6B3E; font-size: 18px; margin-left: 8px;">NT${{ number_format((int) $order->total) }}</strong>
        </div>
      </div>
      <p style="color: #7a5836; font-size: 14px; line-height: 1.7;">
        如果您遇到付款問題，歡迎透過 LINE 客服協助（1 小時內回覆）。
      </p>
      <div style="text-align: center; padding: 16px 0 8px;">
        <a href="{{ config('app.url') }}/order-lookup?order={{ $order->order_number }}"
           style="display: inline-block; padding: 12px 28px; background: #9F6B3E; color: #fff; text-decoration: none; border-radius: 999px; font-weight: bold; margin: 4px;">
          繼續結帳 →
        </a>
        <a href="https://lin.ee/pandorasdo" target="_blank"
           style="display: inline-block; padding: 12px 28px; background: #06C755; color: #fff; text-decoration: none; border-radius: 999px; font-weight: bold; margin: 4px;">
          💬 LINE 客服
        </a>
      </div>
    </div>
    <div style="background: #fdf7ef; padding: 16px 24px; color: #b09070; font-size: 11px; text-align: center;">
      婕樂纖仙女館 JEROSSE · 訂單保留 3 天，過期會自動取消
    </div>
  </div>
</body>
</html>
