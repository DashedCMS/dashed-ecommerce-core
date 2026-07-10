<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('exposes trackAndTraces.code as a globally searchable attribute', function () {
    expect(OrderResource::getGloballySearchableAttributes())->toContain('trackAndTraces.code');
});

it('finds a paid order via global search by its track and trace code', function () {
    Mail::fake();

    $match = Order::create(['invoice_id' => 'INV-TT-1', 'status' => 'paid', 'order_origin' => 'own', 'total' => 10, 'email' => 'a@b.nl']);
    OrderTrackAndTrace::create([
        'order_id' => $match->id,
        'supplier' => 'PostNL',
        'delivery_company' => 'PostNL',
        'code' => '3STOTALLYUNIQUE123',
    ]);

    $other = Order::create(['invoice_id' => 'INV-TT-2', 'status' => 'paid', 'order_origin' => 'own', 'total' => 10, 'email' => 'c@d.nl']);

    $titles = OrderResource::getGlobalSearchResults('3STOTALLYUNIQUE123')->map(fn ($result) => (string) $result->title);

    expect($titles->contains(fn ($title) => str_contains($title, 'INV-TT-1')))->toBeTrue()
        ->and($titles->contains(fn ($title) => str_contains($title, 'INV-TT-2')))->toBeFalse();
});
