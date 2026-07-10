<?php

use App\Models\User;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;
use Dashed\DashedEcommerceCore\Models\Order;
use Livewire\Livewire;

function makeRevenueOrder(string $status, float $total): Order
{
    return Order::withoutEvents(fn () => Order::create([
        'total' => $total,
        'status' => $status,
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));
}

it('telt standaard alleen orders met betalingsverplichting in de omzet', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');

    makeRevenueOrder('paid', 100);
    makeRevenueOrder('cancelled', 999);
    makeRevenueOrder('pending', 500);

    $graphData = Livewire::test(RevenueStatisticsPage::class)->get('graphData');

    // Default = payment_obligation: alleen de betaalde order telt mee,
    // geannuleerd/lopend blijven buiten de omzet.
    expect($graphData['filters']['status'])->toBe('payment_obligation')
        ->and($graphData['data']['ordersAmount'])->toBe(1);
});
