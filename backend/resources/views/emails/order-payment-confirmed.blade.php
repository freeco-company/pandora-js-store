@extends('emails.layout')

@section('title', '已收到您的付款 - ' . $order->order_number)

@section('banner')
    <div style="display: inline-block; background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #166534; font-weight: bold; letter-spacing: 1px;">
        PAYMENT CONFIRMED
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        已收到您的款項，準備為您出貨
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        訂單 <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong>
    </p>
@endsection

@section('content')
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 14px 16px; background-color: #fdf7ef; color: #1f1a15; font-size: 14px; line-height: 1.7;">
                您好，我們已於 <strong>{{ now()->format('Y/m/d') }}</strong> 確認收到您的轉帳款項 <strong style="color: #9F6B3E;">NT${{ number_format($order->total) }}</strong>，訂單進入出貨準備中。<br><br>
                商品預計於 <strong>1–3 個工作天內</strong>寄出，出貨後會再次通知您物流資訊。
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td colspan="2" style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                訂單摘要
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px; width: 100px;">訂單編號</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px; font-weight: bold;">{{ $order->order_number }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">訂購日期</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->created_at?->format('Y/m/d') }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">收件人</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->shipping_name }}</td>
        </tr>
        @if($order->shipping_store_name)
        <tr>
            <td style="padding: 10px 16px; color: #7a5836; font-size: 13px;">取貨門市</td>
            <td style="padding: 10px 16px; color: #1f1a15; font-size: 14px;">{{ $order->shipping_store_name }}</td>
        </tr>
        @elseif($order->shipping_address)
        <tr>
            <td style="padding: 10px 16px; color: #7a5836; font-size: 13px;">配送地址</td>
            <td style="padding: 10px 16px; color: #1f1a15; font-size: 14px;">{{ $order->shipping_address }}</td>
        </tr>
        @endif
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px;">
        <tr>
            <td style="padding: 12px 16px; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 10px; font-size: 12px; color: #7a5836; line-height: 1.6;">
                如有任何問題，歡迎透過 <a href="https://lin.ee/62wj7qa" style="color: #9F6B3E; font-weight: bold; text-decoration: underline;">LINE 客服</a> 聯繫我們。
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/order-lookup?order={{ $order->order_number }}"
       style="display: inline-block; padding: 12px 32px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold;">
        查看訂單詳情
    </a>
@endsection
