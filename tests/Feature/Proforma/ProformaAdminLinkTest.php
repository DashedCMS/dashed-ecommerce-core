<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Borgt de voorwaarden waarop de admin-actie "Proforma-afrekenlink" op de
 * ViewOrder-pagina zichtbaar wordt, plus dat de link naar de klant-checkout
 * daadwerkelijk opgebouwd kan worden. Zo kan de beheerder vanuit een concept-
 * proforma-order de afrekenpagina openen die naar de klant is gestuurd.
 */
it('shows the proforma checkout link condition only for an unpaid proforma concept', function () {
    $proforma = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'first_name' => 'Jan',
        'total' => 121.0,
    ]);

    // Een concept-proforma met hash: link zichtbaar + opbouwbaar.
    $visible = (bool) $proforma->is_proforma && $proforma->isConcept() && (bool) $proforma->hash;
    expect($visible)->toBeTrue()
        ->and(route('dashed.frontend.proforma-checkout', ['orderHash' => $proforma->hash]))
        ->toContain('/proforma/' . $proforma->hash);
});

it('hides the proforma checkout link for a normal (non-proforma) order', function () {
    $normal = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => false,
        'first_name' => 'Piet',
        'total' => 50.0,
    ]);

    $visible = (bool) $normal->is_proforma && $normal->isConcept() && (bool) $normal->hash;
    expect($visible)->toBeFalse();
});
