<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Betaling kon niet gestart worden</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h1 style="color: #b91c1c; margin-top: 0;">Betaling kon niet gestart worden</h1>

    <p>Bij order <strong>#{{ $orderId }}</strong> kon de betaling niet worden gestart via <strong>{{ $psp }}</strong>.</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tbody>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Context</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $context }}</td></tr>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Betaalmethode</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $paymentMethod }}</td></tr>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Bedrag</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $amount }}</td></tr>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Klant</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $customerName }} &lt;{{ $customerEmail }}&gt;</td></tr>
        </tbody>
    </table>

    <h3 style="margin-bottom: 4px;">Foutmelding</h3>
    <pre style="background: #f4f4f4; padding: 12px; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; word-break: break-word;">{{ $exceptionClass }}
{{ $exceptionMessage }}

in {{ $exceptionLocation }}</pre>

    @if(! empty($orderUrl) && $orderUrl !== '#')
        <p style="margin-top: 24px;">
            <a href="{{ $orderUrl }}" style="background: #1f2937; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none;">Bekijk order in CMS</a>
        </p>
    @endif
</body>
</html>
