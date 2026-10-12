<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/FreeShippingReversalFixtures.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderWithdrawal;

beforeEach(function () {
    bootInvoiceHarness();
});

/** Livewire::test() draait niet in dit pakketharnas, dus het component wordt direct opgebouwd. */
function withdrawalComponent(array $f, array $selected): OrderWithdrawal
{
    $component = new OrderWithdrawal();
    $component->foundOrderId = $f['order']->id;
    foreach ([$f['shirt'], $f['broek']] as $line) {
        $component->selectedLines[$line->id] = [
            'selected' => isset($selected[$line->id]),
            'quantity' => $selected[$line->id] ?? $line->quantity,
            'reason_id' => null,
            'note' => '',
        ];
    }

    return $component;
}

it('meldt de klant vooraf dat verzendkosten verrekend worden', function () {
    $f = reversalFixture();

    $component = withdrawalComponent($f, [$f['broek']->id => 1, $f['shirt']->id => 1]);

    // Het harnas heeft geen tabel dashed__translations, dus Translation::get() geeft de
    // standaardtekst terug zonder de :threshold:/:amount:-plaatshouders te vervangen.
    // De bedragen controleren we daarom via het voorstel en de opmaak.
    expect($component->getShippingReversalNoticeProperty())->toContain('Na deze retour komt je bestelling onder');

    $proposal = app(FreeShippingReversal::class)->propose($f['order'], [$f['broek']->id => 1, $f['shirt']->id => 1]);
    expect(CurrencyHelper::formatPrice($proposal->amount))->toContain('7,95');
});

it('meldt niets als de bestelling boven de drempel blijft of alles terugkomt', function () {
    $f = reversalFixture();

    $component = withdrawalComponent($f, [$f['shirt']->id => 1]);
    expect($component->getShippingReversalNoticeProperty())->toBeNull();

    $component = withdrawalComponent($f, [$f['shirt']->id => 3, $f['broek']->id => 1]);
    expect($component->getShippingReversalNoticeProperty())->toBeNull();
});

it('meldt niets zonder geselecteerde regels', function () {
    $f = reversalFixture();

    expect(withdrawalComponent($f, [])->getShippingReversalNoticeProperty())->toBeNull();

    // Zonder selectie wordt de bestelling niet eens geladen.
    $component = withdrawalComponent($f, []);
    $component->foundOrderId = null;
    expect($component->getShippingReversalNoticeProperty())->toBeNull();
});
