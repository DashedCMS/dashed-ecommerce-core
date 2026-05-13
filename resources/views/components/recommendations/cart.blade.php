@props([
    'view' => 'cart',
    'limit' => null,
])

@livewire('cart-recommendations', ['view' => $view, 'limit' => $limit])
