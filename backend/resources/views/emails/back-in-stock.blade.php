@extends('emails.layout')

@section('title', $product->name . ' 已到貨')

@section('banner')
    <div style="display: inline-block; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #059669; font-weight: bold; letter-spacing: 1px;">
        BACK IN STOCK
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        到貨通知
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        你想等的商品回來了！
    </p>
@endsection

@section('content')
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 16px;">
        <tr>
            <td style="padding: 20px 16px; text-align: center;">
                <h3 style="margin: 0 0 8px; font-size: 16px; color: #1f1a15;">{{ $product->name }}</h3>
                <p style="margin: 0; color: #7a5836; font-size: 14px; line-height: 1.7;">
                    {{ $product->short_description ?: '商品現已補貨，點下方連結立即選購。' }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin: 0; color: #b09070; font-size: 12px; text-align: center;">
        庫存有限，建議盡早下單
    </p>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/products/{{ $product->slug }}"
       style="display: inline-block; padding: 12px 32px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold;">
        立即選購
    </a>
@endsection
