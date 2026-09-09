<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="dark light">
<meta name="supported-color-schemes" content="dark light">
<title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#050a12;color:#f7fbff;font-family:Arial,'Segoe UI',Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#050a12" style="width:100%;margin:0;padding:0;background:#050a12;">
<tr><td align="center" style="padding:28px 14px;">
<table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" bgcolor="#081421" style="width:100%;max-width:620px;margin:0 auto;background:#081421;border:1px solid #20364b;border-radius:16px;overflow:hidden;">
<tr><td height="4" bgcolor="#D9AD4B" style="height:4px;background:#D9AD4B;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td align="center" style="padding:28px 28px 24px;border-bottom:1px solid #173047;text-align:center;">
<div style="font-size:20px;line-height:1.25;font-weight:800;letter-spacing:2px;color:#FFFFFF;">ALPHA <span style="color:#D9AD4B;">BLOCK</span> SOLUTIONS</div>
<div style="margin-top:7px;font-size:9px;line-height:1.4;font-weight:700;letter-spacing:3px;color:#8296A9;">DIGITAL MARKET INTELLIGENCE</div>
<div style="display:inline-block;margin-top:17px;padding:6px 10px;border:1px solid #31506A;border-radius:999px;font-size:9px;line-height:1.2;color:#D9AD4B;letter-spacing:1.2px;font-weight:800;text-transform:uppercase;">{{ $eyebrow }}</div>
</td></tr>
<tr><td align="center" style="padding:30px 34px 16px;text-align:center;">
<h1 style="margin:0 0 18px;font-size:28px;line-height:1.2;color:#FFFFFF;font-weight:800;letter-spacing:-.2px;">{{ $title }}</h1>
<p style="margin:0 0 12px;font-size:16px;line-height:1.65;color:#F0F5FA;">{{ $greeting }}</p>
@if($intro)<p style="margin:0 auto 12px;max-width:520px;font-size:15px;line-height:1.7;color:#DCE7F1;">{{ $intro }}</p>@endif
@if($body)<p style="margin:0 auto 20px;max-width:520px;font-size:14px;line-height:1.75;color:#AFC0D0;">{{ $body }}</p>@endif

@if(!empty($bullets))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#071827" style="margin:18px auto 22px;width:100%;max-width:520px;background:#071827;border:1px solid #1A3850;border-radius:12px;">
@foreach($bullets as $item)
<tr><td align="center" style="padding:13px 17px;{{ !$loop->last ? 'border-bottom:1px solid #173047;' : '' }}color:#DCE8F2;font-size:13px;line-height:1.6;text-align:center;"><span style="color:#27D6CF;font-weight:900;">✓</span>&nbsp;&nbsp;{{ $item }}</td></tr>
@endforeach
</table>
@endif

@if(!empty($facts))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#06111D" style="margin:18px auto 24px;width:100%;max-width:520px;background:#06111D;border:1px solid #1D3B52;border-radius:12px;">
@foreach($facts as $label => $value)
@if($value)
<tr><td align="center" style="padding:13px 16px;{{ !$loop->last ? 'border-bottom:1px solid #163044;' : '' }}text-align:center;">
<div style="margin:0 0 4px;color:#8298AA;font-size:9px;line-height:1.35;font-weight:800;letter-spacing:1.1px;text-transform:uppercase;">{{ $label }}</div>
<div style="margin:0;color:#FFFFFF;font-size:13px;line-height:1.5;font-weight:800;word-break:break-word;">{{ $value }}</div>
</td></tr>
@endif
@endforeach
</table>
@endif

@if($buttonText && $buttonUrl)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:26px auto 15px;"><tr>
<td align="center" bgcolor="#E4B94F" style="background:#E4B94F;border-radius:9px;border:1px solid #F0C964;">
<a href="{{ $buttonUrl }}" target="_blank" style="display:inline-block;padding:14px 26px;color:#07111D!important;background:#E4B94F;text-decoration:none;font-family:Arial,'Segoe UI',Helvetica,sans-serif;font-size:14px;line-height:1.2;font-weight:800;border-radius:9px;">{{ $buttonText }} &nbsp;→</a>
</td></tr></table>
<p style="margin:0 auto 22px;max-width:500px;font-size:10px;line-height:1.55;color:#71869A;text-align:center;">If the button does not open, copy this secure link:<br><a href="{{ $buttonUrl }}" target="_blank" style="color:#6FDDE0;text-decoration:underline;word-break:break-all;">{{ $buttonUrl }}</a></p>
@endif

@if($notice)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#071827" style="margin:18px auto 10px;width:100%;max-width:520px;background:#071827;border:1px solid #1C4057;border-radius:10px;"><tr><td align="center" style="padding:14px 16px;color:#A9BBCB;font-size:12px;line-height:1.65;text-align:center;">{{ $notice }}</td></tr></table>
@endif
</td></tr>
<tr><td align="center" style="padding:22px 28px 27px;border-top:1px solid #173149;background:#06101B;color:#7E93A6;font-size:11px;line-height:1.7;text-align:center;">
<p style="margin:0 0 7px;">Need help? <a href="mailto:{{ $supportEmail }}" style="color:#5ED9D8;text-decoration:none;font-weight:700;">{{ $supportEmail }}</a></p>
<p style="margin:0 0 8px;"><a href="{{ $websiteUrl }}" style="color:#D9AD4B;text-decoration:none;font-weight:700;">{{ $companyName }}</a></p>
<p style="margin:0 auto 8px;max-width:510px;color:#71869A;">Market information, economic-event context and Pulse signals are informational and educational only, not financial advice. Digital assets involve substantial risk and outcomes are never guaranteed.</p>
<p style="margin:0;color:#5F7487;">© {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
