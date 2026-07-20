<?php

use App\Models\User;
use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Widgets\OrderUnhandledStat;

function makeUnhandledStatOrder(string $status, string $fulfillmentStatus): Order
{
    return Order::withoutEvents(fn () => Order::create([
        'total' => 10.00,
        'status' => $status,
        'fulfillment_status' => $fulfillmentStatus,
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));
}

it('telt alleen betaalde orders die op unhandled staan', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');

    // Tellen mee:
    makeUnhandledStatOrder('paid', 'unhandled');
    makeUnhandledStatOrder('partially_paid', 'unhandled');

    // Tellen niet mee: al in behandeling/ingepakt/verzonden/afgehandeld:
    makeUnhandledStatOrder('paid', 'in_treatment');
    makeUnhandledStatOrder('paid', 'packed');
    makeUnhandledStatOrder('paid', 'shipped');
    makeUnhandledStatOrder('paid', 'handled');
    // Niet betaald:
    makeUnhandledStatOrder('pending', 'unhandled');

    $component = Livewire::test(OrderUnhandledStat::class);
    $stats = (new ReflectionMethod(OrderUnhandledStat::class, 'getStats'))->invoke($component->instance());

    expect($stats[0]->getValue())->toBe('2');
});
