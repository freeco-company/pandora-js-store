@extends('emails.layout')

@section('title', '訂單已完成 - ' . $order->order_number)

@section('banner')
    <div style="display: inline-block; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #9F6B3E; font-weight: bold; letter-spacing: 1px;">
        ORDER COMPLETED
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        感謝妳，訂單已順利完成
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        訂單 <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong>
    </p>
@endsection

@section('content')
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 14px 16px; background-color: #fdf7ef; color: #1f1a15; font-size: 14px; line-height: 1.7;">
                希望這次選的商品妳會喜歡。
                <br><br>
                如果方便，到訂單頁留下使用心得，能幫到還在猶豫的朋友 — 也讓我們知道哪裡能做得更好。
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px; margin-bottom: 20px;">
        <tr>
            <td style="padding: 12px 16px; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 10px; font-size: 12px; color: #7a5836; line-height: 1.6;">
                有任何問題請透過 <a href="https://lin.ee/62wj7qa" style="color: #9F6B3E; font-weight: bold; text-decoration: underline;">LINE 客服</a> 聯繫我們，鑑賞期內可申請退換貨。
            </td>
        </tr>
    </table>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/order-lookup?order={{ $order->order_number }}#review"
       style="display: inline-block; padding: 12px 32px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold;">
        留下使用心得
    </a>
@endsection
