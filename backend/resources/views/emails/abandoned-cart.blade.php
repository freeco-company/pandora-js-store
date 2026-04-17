@extends('emails.layout')

@section('title', '訂單 ' . $order->order_number . ' 尚未完成')

@section('banner')
    <div style="display: inline-block; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #d97706; font-weight: bold; letter-spacing: 1px;">
        REMINDER
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        訂單尚未付款
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        還差一步就完成囉！
    </p>
@endsection

@section('content')
    <p style="margin: 0 0 16px; color: #7a5836; font-size: 14px; line-height: 1.7;">
        您在 <strong>{{ $order->created_at->format('Y/m/d H:i') }}</strong> 建立了訂單
        <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong>，目前還未完成付款。
    </p>

    {{-- Order Items --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td colspan="2" style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                訂單內容
            </td>
        </tr>
        @foreach ($order->items as $item)
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 13px; line-height: 1.4;">{{ $item->product_name }}</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px; text-align: right; white-space: nowrap;">x{{ $item->quantity }}</td>
        </tr>
        @endforeach
        <tr>
            <td style="padding: 14px 16px; color: #7a5836; font-size: 14px; border-top: 2px solid #9F6B3E; font-weight: bold;">應付金額</td>
            <td style="padding: 14px 16px; color: #9F6B3E; font-size: 20px; text-align: right; border-top: 2px solid #9F6B3E; font-weight: bold;">NT${{ number_format((int) $order->total) }}</td>
        </tr>
    </table>

    <p style="margin: 0; color: #7a5836; font-size: 14px; line-height: 1.7;">
        如果您遇到付款問題，歡迎透過 LINE 客服協助（1 小時內回覆）。
    </p>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/order-lookup?order={{ $order->order_number }}"
       style="display: inline-block; padding: 12px 28px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold; margin: 4px;">
        繼續結帳
    </a>
@endsection
