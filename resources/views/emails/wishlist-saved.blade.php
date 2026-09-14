<!doctype html>
<html lang="nl">
<body style="margin:0;padding:24px;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#18181b;">
<table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;">
    <tr><td style="padding:32px;">
        <h1 style="font-size:20px;margin:0 0 12px;">{{ __('Je verlanglijst is bewaard') }}</h1>
        <p style="font-size:14px;line-height:1.5;margin:0 0 20px;">{{ __('Met de knop hieronder open je je verlanglijst op elk apparaat. Dit staat erop:') }}</p>
        @foreach($items as $item)
            <p style="font-size:14px;margin:0 0 6px;">&bull; <a href="{{ $item->product->getUrl() }}" style="color:#18181b;">{{ $item->product->name }}</a></p>
        @endforeach
        <p style="margin:24px 0 0;">
            <a href="{{ $restoreUrl }}" style="display:inline-block;padding:12px 24px;background:{{ $primaryColor }};color:#ffffff;text-decoration:none;border-radius:6px;font-weight:bold;">{{ __('Bekijk je verlanglijst') }}</a>
        </p>
    </td></tr>
</table>
</body>
</html>
