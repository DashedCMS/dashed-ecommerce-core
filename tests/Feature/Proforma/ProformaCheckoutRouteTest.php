<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Controllers\Frontend\ProformaCheckoutController;

/**
 * De route/guard wordt op controller-niveau getoetst: de volledige site-layout
 * (header/head) heeft site-data uit de DB nodig die in de testomgeving niet
 * geseed is, dus we asserten welke view de controller kiest i.p.v. de hele
 * pagina te renderen. De visuele opmaak hangt aan de echte checkout-layout.
 */
it('aborts (404) for a non-proforma order hash', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => false]);

    expect(fn () => (new ProformaCheckoutController())->show(request(), $order->hash))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('serves the checkout view for an unpaid proforma', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);

    $view = (new ProformaCheckoutController())->show(request(), $order->hash);

    expect($view->name())->toBe('dashed-ecommerce-core::proforma.checkout')
        ->and($view->getData()['order']->id)->toBe($order->id);
});

it('serves the already-paid view for a paid proforma', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'is_proforma' => true]);

    $view = (new ProformaCheckoutController())->show(request(), $order->hash);

    expect($view->name())->toBe('dashed-ecommerce-core::proforma.already-paid');
});
