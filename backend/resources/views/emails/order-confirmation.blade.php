<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單確認 - {{ $order->order_number }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: 'Microsoft JhengHei', 'PingFang TC', Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 24px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; max-width: 100%;">
                    {{-- Header --}}
                    <tr>
                        <td style="background-color: #9F6B3E; padding: 28px 32px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: bold; letter-spacing: 1px;">
                                婕樂纖仙女館
                            </h1>
                            <p style="margin: 8px 0 0; color: rgba(255,255,255,0.85); font-size: 14px;">
                                訂單確認通知
                            </p>
                        </td>
                    </tr>

                    {{-- Order Summary --}}
                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 8px; color: #333; font-size: 16px;">
                                感謝您的訂購！以下是您的訂單資訊：
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #eee; border-radius: 6px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #faf7f4; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">訂單編號</td>
                                    <td style="padding: 12px 16px; background-color: #faf7f4; border-bottom: 1px solid #eee; color: #333; font-size: 14px; font-weight: bold;">{{ $order->order_number }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">訂單日期</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">{{ $order->created_at->format('Y/m/d H:i') }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">訂單狀態</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">
                                        @switch($order->status)
                                            @case('pending') 待處理 @break
                                            @case('processing') 處理中 @break
                                            @case('shipped') 已出貨 @break
                                            @case('completed') 已完成 @break
                                            @case('cancelled') 已取消 @break
                                            @default {{ $order->status }}
                                        @endswitch
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">價格方案</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">
                                        @switch($order->pricing_tier)
                                            @case('retail') 原價 @break
                                            @case('combo') 1+1 搭配價 @break
                                            @case('vip') VIP 優惠價 @break
                                            @default {{ $order->pricing_tier }}
                                        @endswitch
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">付款方式</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">
                                        @switch($order->payment_method)
                                            @case('ecpay_credit') 信用卡付款 @break
                                            @case('cod') 貨到付款 @break
                                            @case('bank_transfer') 銀行轉帳 @break
                                            @default {{ $order->payment_method }}
                                        @endswitch
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; color: #666; font-size: 13px;">配送方式</td>
                                    <td style="padding: 12px 16px; color: #333; font-size: 14px;">
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
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #eee; border-radius: 6px; overflow: hidden;">
                                <tr>
                                    <td colspan="2" style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 14px; font-weight: bold;">
                                        收件資訊
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">收件人</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">{{ $order->shipping_name }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">聯絡電話</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">{{ $order->shipping_phone }}</td>
                                </tr>
                                @if($order->shipping_store_name)
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #666; font-size: 13px;">取貨門市</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">{{ $order->shipping_store_name }}</td>
                                </tr>
                                @endif
                                @if($order->shipping_address)
                                <tr>
                                    <td style="padding: 12px 16px; color: #666; font-size: 13px;">配送地址</td>
                                    <td style="padding: 12px 16px; color: #333; font-size: 14px;">{{ $order->shipping_address }}</td>
                                </tr>
                                @endif
                            </table>

                            {{-- Items Table --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border: 1px solid #eee; border-radius: 6px; overflow: hidden;">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 14px; font-weight: bold;">商品名稱</td>
                                    <td style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 14px; font-weight: bold; text-align: center;">數量</td>
                                    <td style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 14px; font-weight: bold; text-align: right;">單價</td>
                                    <td style="padding: 12px 16px; background-color: #9F6B3E; color: #fff; font-size: 14px; font-weight: bold; text-align: right;">小計</td>
                                </tr>
                                @foreach($order->items as $item)
                                <tr>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px;">{{ $item->product_name }}</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; text-align: center;">{{ $item->quantity }}</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; text-align: right;">NT${{ number_format($item->unit_price) }}</td>
                                    <td style="padding: 12px 16px; border-bottom: 1px solid #eee; color: #333; font-size: 14px; text-align: right;">NT${{ number_format($item->subtotal) }}</td>
                                </tr>
                                @endforeach
                            </table>

                            {{-- Totals --}}
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 20px;">
                                <tr>
                                    <td style="padding: 8px 16px; color: #666; font-size: 14px;">商品小計</td>
                                    <td style="padding: 8px 16px; color: #333; font-size: 14px; text-align: right;">NT${{ number_format($order->subtotal) }}</td>
                                </tr>
                                @if($order->discount > 0)
                                <tr>
                                    <td style="padding: 8px 16px; color: #9F6B3E; font-size: 14px;">優惠折扣</td>
                                    <td style="padding: 8px 16px; color: #9F6B3E; font-size: 14px; text-align: right;">-NT${{ number_format($order->discount) }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding: 8px 16px; color: #666; font-size: 14px;">運費</td>
                                    <td style="padding: 8px 16px; color: #333; font-size: 14px; text-align: right;">
                                        @if($order->shipping_fee > 0)
                                            NT${{ number_format($order->shipping_fee) }}
                                        @else
                                            免運費
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 16px; border-top: 2px solid #9F6B3E; color: #333; font-size: 18px; font-weight: bold;">訂單總計</td>
                                    <td style="padding: 12px 16px; border-top: 2px solid #9F6B3E; color: #9F6B3E; font-size: 18px; font-weight: bold; text-align: right;">NT${{ number_format($order->total) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #faf7f4; padding: 24px 32px; text-align: center; border-top: 1px solid #eee;">
                            <p style="margin: 0 0 8px; color: #666; font-size: 13px;">
                                如有任何問題，請聯繫
                                <a href="mailto:contact@freeco.cc" style="color: #9F6B3E; text-decoration: none;">contact@freeco.cc</a>
                            </p>
                            <p style="margin: 0; color: #999; font-size: 12px;">
                                &copy; {{ date('Y') }} 婕樂纖仙女館 JEROSSE. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
