<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Klant loopt vast in de checkout</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; line-height: 1.5; max-width: 640px; margin: 0 auto; padding: 24px;">
    <h1 style="color: #b91c1c; margin-top: 0;">Klant loopt vast in de checkout</h1>

    <p>Winkelwagen <strong>#{{ $cartId }}</strong> komt niet door de controle van het afrekenformulier.</p>

    <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
        <tbody>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Klant</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $customerEmail }}</td></tr>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Bedrag</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $cartTotal }}</td></tr>
            <tr><td style="padding: 6px 12px; border: 1px solid #e5e7eb;"><strong>Pogingen</strong></td><td style="padding: 6px 12px; border: 1px solid #e5e7eb;">{{ $attempts }}</td></tr>
        </tbody>
    </table>

    <h3 style="margin-bottom: 4px;">Fouten bij de laatste poging</h3>
    <ul style="padding-left: 20px;">
        @foreach($errorLines as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>

    @if($cartUrl !== '#')
        <p style="margin-top: 24px;">
            <a href="{{ $cartUrl }}" style="background: #1f2937; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none;">Bekijk winkelwagen in CMS</a>
        </p>
    @endif
</body>
</html>
