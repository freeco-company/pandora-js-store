@extends('emails.layout')

@section('title', '分享您的使用心得')

@section('banner')
    <div style="display: inline-block; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #9F6B3E; font-weight: bold; letter-spacing: 1px;">
        REVIEW
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        用得還滿意嗎？
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        花 30 秒留下評價，幫助其他仙女選購
    </p>
@endsection

@section('content')
    <p style="margin: 0 0 16px; color: #7a5836; font-size: 14px; line-height: 1.7;">
        {{ $order->shipping_name }}您好，您的訂單
        <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong>
        已送達一段時間，想聽聽您的使用體驗！
    </p>

    {{-- Product list --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                等待您評價的商品
            </td>
        </tr>
        @foreach ($productNames as $name)
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 13px; line-height: 1.4;">
                ⭐ {{ $name }}
            </td>
        </tr>
        @endforeach
    </table>

    <p style="margin: 0; color: #7a5836; font-size: 14px; line-height: 1.7;">
        您的真實回饋是我們最珍貴的口碑 🌸
    </p>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/account?tab=reviews"
       style="display: inline-block; padding: 12px 28px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold; margin: 4px;">
        立即評價
    </a>
@endsection
