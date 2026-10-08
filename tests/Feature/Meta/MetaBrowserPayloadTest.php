<?php

use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
use Dashed\DashedEcommerceCore\Services\Meta\MetaBrowserPayload;

function browserOrder(): Order
{
    $order = Order::create(['status' => 'paid', 'total' => 35.03, 'subtotal' => 35.03, 'btw' => 6.08, 'discount' => 0]);

    // Product 501 bestaat niet in de testdatabase; alleen de FK-controle uit.
    Schema::disableForeignKeyConstraints();
    OrderProduct::create(['order_id' => $order->id, 'product_id' => 501, 'name' => 'Vaas', 'quantity' => 1, 'price' => 27.08, 'btw' => 4.70]);
    OrderProduct::create(['order_id' => $order->id, 'product_id' => null, 'sku' => 'shipping_costs', 'name' => 'Verzending', 'quantity' => 1, 'price' => 7.95, 'btw' => 1.38]);
    Schema::enableForeignKeyConstraints();

    return $order->fresh(['orderProducts']);
}

it('geeft de browser hetzelfde event-ID als de server', function () {
    $order = browserOrder();

    expect(MetaBrowserPayload::forOrder($order)['metaEventId'])
        ->toBe('purchase_' . $order->id)
        ->toBe(ConversionsApi::eventId($order));
});

it('stuurt met de Conversions API uit de waarde die de pixel altijd al stuurde', function () {
    Customsetting::set('meta_capi_value_mode', 'excl_vat', Sites::getActive());

    expect(MetaBrowserPayload::forOrder(browserOrder())['metaValue'])->toBe('35.03');
});

it('volgt de value mode zodra de Conversions API aan staat', function () {
    Customsetting::set('meta_capi_enabled', true, Sites::getActive());
    Customsetting::set('meta_capi_value_mode', 'excl_vat_excl_shipping', Sites::getActive());

    expect(MetaBrowserPayload::forOrder(browserOrder())['metaValue'])->toBe('22.38');
});

it('geeft dezelfde contents als de server', function () {
    expect(MetaBrowserPayload::forOrder(browserOrder())['metaContents'])
        ->toBe([['id' => '501', 'quantity' => 1, 'item_price' => 27.08]]);
});

it('geeft het event-ID door aan fbq in de Purchase-listener', function () {
    $blade = file_get_contents(__DIR__ . '/../../../resources/views/components/frontend/body-extend.blade.php');

    expect($blade)->toContain('eventID: payload.metaEventId')
        ->and($blade)->toContain('payload.metaValue');
});

it('zet de Meta-velden in de orderPaid-payload van de bedankpagina', function () {
    $component = file_get_contents(__DIR__ . '/../../../src/Livewire/Frontend/Orders/ViewOrder.php');

    expect($component)->toContain('MetaBrowserPayload::forOrder($this->order)');
});
