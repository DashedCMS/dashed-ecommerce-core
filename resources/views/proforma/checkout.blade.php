<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Proforma bestelling betalen') }}</title>
    @livewireStyles
</head>
<body>
    @livewire('checkout.proforma-checkout', ['orderHash' => $order->hash])
    @livewireScripts
</body>
</html>
