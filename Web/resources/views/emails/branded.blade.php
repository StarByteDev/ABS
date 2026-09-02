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
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#050a12" style="width:100%;background:#050a12;margin:0;padding:0;">
<tr><td align="center" style="padding:32px 12px;">
<table role="presentation" width="700" cellspacing="0" cellpadding="0" border="0" bgcolor="#081421" style="width:100%;max-width:700px;background:#081421;border:1px solid #20364b;border-radius:16px;overflow:hidden;">
<tr><td height="4" bgcolor="#D9AD4B" style="height:4px;background:#D9AD4B;font-size:0;line-height:0;">&nbsp;</td></tr>
<tr><td style="padding:27px 32px 22px;border-bottom:1px solid #173047;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
<td valign="top">
<div style="font-size:19px;line-height:1.25;font-weight:800;letter-spacing:2px;color:#FFFFFF;">ALPHA <span style="color:#D9AD4B;">BLOCK</span> SOLUTIONS</div>
<div style="margin-top:6px;font-size:10px;line-height:1.4;font-weight:700;letter-spacing:3px;color:#8296A9;">DIGITAL MARKET INTELLIGENCE</div>
</td>
<td valign="top" align="right" style="padding-left:16px;font-size:10px;line-height:1.4;color:#D9AD4B;letter-spacing:1.3px;font-weight:800;text-transform:uppercase;">{{ $eyebrow }}</td>
</tr></table>
</td></tr>
<tr><td style="padding:30px 32px 10px;">
<h1 style="margin:0 0 18px;font-size:30px;line-height:1.18;color:#FFFFFF;font-weight:800;letter-spacing:-.3px;">{{ $title }}</h1>
<p style="margin:0 0 13px;font-size:16px;line-height:1.65;color:#F0F5FA;">{{ $greeting }}</p>
@if($intro)<p style="margin:0 0 12px;font-size:16px;line-height:1.68;color:#E7EEF6;">{{ $intro }}</p>@endif
@if($body)<p style="margin:0 0 20px;font-size:15px;line-height:1.72;color:#AEC0D1;">{{ $body }}</p>@endif

@if(!empty($bullets))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#071827" style="margin:7px 0 22px;width:100%;background:#071827;border:1px solid #1A3850;border-radius:12px;">
@foreach($bullets as $item)
<tr>
<td valign="top" width="35" style="width:35px;padding:13px 0 13px 15px;color:#27D6CF;font-size:17px;line-height:1.3;font-weight:900;">✓</td>
<td valign="top" style="padding:13px 15px 13px 7px;{{ !$loop->last ? 'border-bottom:1px solid #173047;' : '' }}color:#DCE8F2;font-size:14px;line-height:1.55;">{{ $item }}</td>
</tr>
@endforeach
</table>
@endif

@if(!empty($facts))
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#06111D" style="margin:6px 0 24px;width:100%;background:#06111D;border:1px solid #1D3B52;border-radius:12px;">
@foreach($facts as $label => $value)
@if($value)
<tr>
<td style="padding:12px 15px;{{ !$loop->last ? 'border-bottom:1px solid #163044;' : '' }}color:#8CA0B3;font-size:13px;line-height:1.45;">{{ $label }}</td>
<td align="right" style="padding:12px 15px;{{ !$loop->last ? 'border-bottom:1px solid #163044;' : '' }}color:#FFFFFF;font-size:13px;line-height:1.45;font-weight:800;">{{ $value }}</td>
</tr>
@endif
@endforeach
</table>
@endif

@if($buttonText && $buttonUrl)
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:27px 0 18px;"><tr>
<td align="center" bgcolor="#E4B94F" style="background:#E4B94F;border-radius:9px;border:1px solid #F0C964;">
<a href="{{ $buttonUrl }}" target="_blank" style="display:inline-block;padding:14px 25px;color:#07111D!important;background:#E4B94F;text-decoration:none;font-family:Arial,'Segoe UI',Helvetica,sans-serif;font-size:14px;line-height:1.2;font-weight:800;">{{ $buttonText }} &nbsp;→</a>
</td></tr></table>
<p style="margin:0 0 23px;font-size:11px;line-height:1.55;color:#7F95A8;">If the button does not open, use this secure link:<br><a href="{{ $buttonUrl }}" target="_blank" style="color:#6FDDE0;text-decoration:underline;word-break:break-all;">{{ $buttonUrl }}</a></p>
@endif

@if($notice)
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#071827" style="margin:20px 0 10px;width:100%;background:#071827;border-left:3px solid #27D6CF;border-radius:8px;"><tr><td style="padding:14px 16px;color:#A9BBCB;font-size:13px;line-height:1.62;">{{ $notice }}</td></tr></table>
@endif
</td></tr>
<tr><td style="padding:22px 32px 27px;border-top:1px solid #173149;background:#06101B;color:#7E93A6;font-size:12px;line-height:1.65;">
<p style="margin:0 0 7px;">Need account help? <a href="mailto:{{ $supportEmail }}" style="color:#5ED9D8;text-decoration:none;font-weight:700;">{{ $supportEmail }}</a></p>
<p style="margin:0 0 7px;"><a href="{{ $websiteUrl }}" style="color:#D9AD4B;text-decoration:none;font-weight:700;">{{ $companyName }}</a> · {{ $websiteUrl }}</p>
<p style="margin:0;color:#687E91;">© {{ date('Y') }} {{ $companyName }}. This account or service message relates to your use of Alpha Block Solutions.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
