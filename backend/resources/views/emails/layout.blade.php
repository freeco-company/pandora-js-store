<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7eee3; font-family: 'Microsoft JhengHei', '微軟正黑體', 'PingFang TC', 'Noto Sans TC', Arial, sans-serif; -webkit-text-size-adjust: 100%;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f7eee3; padding: 32px 16px;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; max-width: 100%; box-shadow: 0 2px 12px rgba(159,107,62,0.08);">

                    {{-- Logo Header --}}
                    <tr>
                        <td style="padding: 32px 32px 0; text-align: center;">
                            <a href="{{ config('services.ecpay.frontend_url', config('app.url')) }}" style="text-decoration: none;">
                                <img src="{{ config('services.ecpay.frontend_url', config('app.url')) }}/logo.png"
                                     alt="婕樂纖仙女館"
                                     width="64" height="64"
                                     style="display: block; margin: 0 auto 12px; border-radius: 50%; border: 2px solid #e7d9cb;" />
                            </a>
                            <div style="font-size: 18px; font-weight: bold; color: #1f1a15; letter-spacing: 1px;">
                                婕樂纖<span style="color: #9F6B3E;">仙女館</span>
                            </div>
                            <div style="font-size: 10px; font-weight: bold; letter-spacing: 3px; color: #b09070; margin-top: 4px;">
                                FAIRY PANDORA
                            </div>
                        </td>
                    </tr>

                    {{-- Divider --}}
                    <tr>
                        <td style="padding: 20px 32px 0;">
                            <div style="border-top: 1px solid #e7d9cb;"></div>
                        </td>
                    </tr>

                    {{-- Banner --}}
                    @hasSection('banner')
                    <tr>
                        <td style="padding: 24px 32px 0; text-align: center;">
                            @yield('banner')
                        </td>
                    </tr>
                    @endif

                    {{-- Main Content --}}
                    <tr>
                        <td style="padding: 24px 32px 32px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- CTA Buttons --}}
                    @hasSection('cta')
                    <tr>
                        <td style="padding: 0 32px 28px; text-align: center;">
                            @yield('cta')
                        </td>
                    </tr>
                    @endif

                    {{-- Footer --}}
                    <tr>
                        <td style="background-color: #fdf7ef; border-top: 1px solid #e7d9cb; padding: 24px 32px;">
                            {{-- Social Links --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 16px;">
                                        <!--[if mso]>
                                        <table cellpadding="0" cellspacing="0"><tr>
                                        <td style="padding: 0 6px;">
                                        <![endif]-->
                                        <a href="{{ config('services.ecpay.frontend_url', config('app.url')) }}/order-lookup"
                                           style="display: inline-block; padding: 10px 20px; background-color: #9F6B3E; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: bold; margin: 4px;">
                                            訂單查詢
                                        </a>
                                        <!--[if mso]></td><td style="padding: 0 6px;"><![endif]-->
                                        <a href="https://lin.ee/62wj7qa" target="_blank"
                                           style="display: inline-block; padding: 10px 20px; background-color: #06C755; color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: bold; margin: 4px;">
                                            LINE 客服
                                        </a>
                                        <!--[if mso]></td><td style="padding: 0 6px;"><![endif]-->
                                        <a href="https://www.instagram.com/pandorasdo/" target="_blank"
                                           style="display: inline-block; padding: 10px 20px; background: linear-gradient(45deg, #f09433, #e6683c, #dc2743, #cc2366, #bc1888); color: #ffffff; text-decoration: none; border-radius: 999px; font-size: 13px; font-weight: bold; margin: 4px;">
                                            IG 追蹤
                                        </a>
                                        <!--[if mso]></td></tr></table><![endif]-->
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0; color: #b09070; font-size: 11px; text-align: center; line-height: 1.6;">
                                婕樂纖仙女館 JEROSSE · 官方正品授權經銷<br>
                                &copy; {{ date('Y') }} Fairy Pandora. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
