@extends('emails.layout')

@section('title', '商品已出貨 - ' . $order->order_number)

@section('banner')
    <div style="display: inline-block; background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #1d4ed8; font-weight: bold; letter-spacing: 1px;">
        SHIPPED
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        妳的商品已寄出囉
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        訂單 <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong>
    </p>
@endsection

@section('content')
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 14px 16px; background-color: #fdf7ef; color: #1f1a15; font-size: 14px; line-height: 1.7;">
                訂單已於 <strong>{{ now()->format('Y/m/d') }}</strong> 完成出貨。
                @if($order->shipping_store_name)
                    <br>商品送達門市後，超商會發送取貨通知簡訊給妳。
                @else
                    <br>宅配通常 1–3 個工作天內送達，請留意物流通知。
                @endif
            </td>
        </tr>
    </table>

    @if($order->shipping_store_name && $order->cvs_payment_no)
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 2px solid #9F6B3E; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                超商取貨資訊（請保留此頁）
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; background-color: #fff;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding: 4px 0; color: #7a5836; font-size: 13px; width: 100px;">取貨門市</td>
                        <td style="padding: 4px 0; color: #1f1a15; font-size: 14px; font-weight: bold;">{{ $order->shipping_store_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #7a5836; font-size: 13px;">取貨代碼</td>
                        <td style="padding: 4px 0; color: #c0392b; font-size: 18px; font-weight: bold; letter-spacing: 2px; font-family: monospace;">{{ $order->cvs_payment_no }}</td>
                    </tr>
                    @if($order->cvs_validation_no)
                    <tr>
                        <td style="padding: 4px 0; color: #7a5836; font-size: 13px;">驗證碼</td>
                        <td style="padding: 4px 0; color: #c0392b; font-size: 18px; font-weight: bold; letter-spacing: 2px; font-family: monospace;">{{ $order->cvs_validation_no }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
    @endif

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
        @if(! $order->shipping_store_name && $order->shipping_address)
        <tr>
            <td style="padding: 10px 16px; color: #7a5836; font-size: 13px;">配送地址</td>
            <td style="padding: 10px 16px; color: #1f1a15; font-size: 14px;">{{ $order->shipping_address }}</td>
        </tr>
        @endif
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 12px;">
        <tr>
            <td style="padding: 12px 16px; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 10px; font-size: 12px; color: #7a5836; line-height: 1.6;">
                收到商品後，歡迎到訂單頁留下評價，讓更多朋友認識妳的選擇。<br>
                有任何問題請透過 <a href="https://lin.ee/62wj7qa" style="color: #9F6B3E; font-weight: bold; text-decoration: underline;">LINE 客服</a> 聯繫我們。
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
