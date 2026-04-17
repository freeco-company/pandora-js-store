@extends('emails.layout')

@section('title', '訂單確認 - ' . $order->order_number)

@section('banner')
    <div style="display: inline-block; background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 999px; padding: 6px 18px; font-size: 12px; color: #9F6B3E; font-weight: bold; letter-spacing: 1px;">
        ORDER CONFIRMED
    </div>
    <h2 style="margin: 12px 0 0; font-size: 20px; color: #1f1a15; font-weight: bold;">
        感謝您的訂購！
    </h2>
    <p style="margin: 6px 0 0; font-size: 14px; color: #7a5836;">
        訂單 <strong style="color: #9F6B3E;">#{{ $order->order_number }}</strong> 已成功建立
    </p>
@endsection

@section('content')
    {{-- Order Info --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td colspan="2" style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                訂單資訊
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px; width: 100px;">訂單編號</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px; font-weight: bold;">{{ $order->order_number }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">訂單日期</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->created_at->format('Y/m/d H:i') }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">訂單狀態</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">
                @switch($order->status)
                    @case('pending') <span style="color: #d97706;">待處理</span> @break
                    @case('processing') <span style="color: #2563eb;">處理中</span> @break
                    @case('shipped') <span style="color: #059669;">已出貨</span> @break
                    @case('completed') <span style="color: #059669;">已完成</span> @break
                    @case('cancelled') <span style="color: #dc2626;">已取消</span> @break
                    @default {{ $order->status }}
                @endswitch
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">價格方案</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">
                @switch($order->pricing_tier)
                    @case('retail') 原價 @break
                    @case('combo') <span style="color: #9F6B3E; font-weight: bold;">1+1 搭配價</span> @break
                    @case('vip') <span style="color: #9F6B3E; font-weight: bold;">VIP 優惠價</span> @break
                    @default {{ $order->pricing_tier }}
                @endswitch
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">付款方式</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">
                @switch($order->payment_method)
                    @case('ecpay_credit') 信用卡付款 @break
                    @case('cod') 貨到付款 @break
                    @case('bank_transfer') 銀行轉帳 @break
                    @default {{ $order->payment_method }}
                @endswitch
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; color: #7a5836; font-size: 13px;">配送方式</td>
            <td style="padding: 10px 16px; color: #1f1a15; font-size: 14px;">
                @switch($order->shipping_method)
                    @case('cvs_711') 7-ELEVEN 超商取貨 @break
                    @case('cvs_family') 全家超商取貨 @break
                    @case('home_delivery') 宅配到府 @break
                    @default {{ $order->shipping_method }}
                @endswitch
            </td>
        </tr>
    </table>

    {{-- Shipping Info --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td colspan="2" style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; letter-spacing: 0.5px;">
                收件資訊
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px; width: 100px;">收件人</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->shipping_name }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">聯絡電話</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->shipping_phone }}</td>
        </tr>
        @if($order->shipping_store_name)
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px;">取貨門市</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px;">{{ $order->shipping_store_name }}</td>
        </tr>
        @endif
        @if($order->shipping_address)
        <tr>
            <td style="padding: 10px 16px; color: #7a5836; font-size: 13px;">配送地址</td>
            <td style="padding: 10px 16px; color: #1f1a15; font-size: 14px;">{{ $order->shipping_address }}</td>
        </tr>
        @endif
    </table>

    {{-- Items Table --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden; margin-bottom: 20px;">
        <tr>
            <td style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold;">商品名稱</td>
            <td style="padding: 10px 12px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; text-align: center; width: 50px;">數量</td>
            <td style="padding: 10px 12px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; text-align: right; width: 80px;">單價</td>
            <td style="padding: 10px 16px; background-color: #9F6B3E; color: #fff; font-size: 13px; font-weight: bold; text-align: right; width: 80px;">小計</td>
        </tr>
        @foreach($order->items as $item)
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 13px; line-height: 1.4;">{{ $item->product_name }}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 13px; text-align: center;">x{{ $item->quantity }}</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 13px; text-align: right;">NT${{ number_format($item->unit_price) }}</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 13px; text-align: right; font-weight: bold;">NT${{ number_format($item->subtotal) }}</td>
        </tr>
        @endforeach
    </table>

    {{-- Totals --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #fdf7ef; border: 1px solid #e7d9cb; border-radius: 12px; overflow: hidden;">
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 14px;">商品小計</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px; text-align: right;">NT${{ number_format($order->subtotal) }}</td>
        </tr>
        @if($order->discount > 0)
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #9F6B3E; font-size: 14px;">優惠折扣</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #9F6B3E; font-size: 14px; text-align: right;">-NT${{ number_format($order->discount) }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #7a5836; font-size: 14px;">運費</td>
            <td style="padding: 10px 16px; border-bottom: 1px solid #f0e8df; color: #1f1a15; font-size: 14px; text-align: right;">
                @if($order->shipping_fee > 0)
                    NT${{ number_format($order->shipping_fee) }}
                @else
                    <span style="color: #059669;">免運費</span>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; color: #1f1a15; font-size: 16px; font-weight: bold; border-top: 2px solid #9F6B3E;">應付金額</td>
            <td style="padding: 14px 16px; color: #9F6B3E; font-size: 20px; font-weight: bold; text-align: right; border-top: 2px solid #9F6B3E;">NT${{ number_format($order->total) }}</td>
        </tr>
    </table>
@endsection

@section('cta')
    <a href="{{ config('services.frontend.url', config('app.url')) }}/order-lookup?order={{ $order->order_number }}"
       style="display: inline-block; padding: 12px 32px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 14px; font-weight: bold;">
        查看訂單詳情
    </a>
@endsection
