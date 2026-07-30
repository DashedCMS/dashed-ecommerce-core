<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Mail\OrderModifiedMail;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mailableOrder(): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'PROFORMA',
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    return $order->fresh();
}

function mailableConceptOrder(): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);

    return $order->fresh();
}

function mailLine(float $price): array
{
    return ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => $price, 'vat_rate' => 21, 'product_extras' => []];
}

it('stuurt standaard een wijzigingsmail naar de klant', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)]);

    Mail::assertSent(OrderModifiedMail::class, fn ($mail) => $mail->hasTo('klant@example.com'));
});

it('stuurt geen mail wanneer die onderdrukt is', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    Mail::assertNothingSent();
});

it('neemt de toelichting mee in de mail', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['customer_note' => 'Het blauwe model was op.']);

    Mail::assertSent(OrderModifiedMail::class, fn ($mail) => $mail->note === 'Het blauwe model was op.');
});

it('rendert het openstaande bedrag en de betaallink in de mail', function () {
    $old = mailableOrder();
    $new = OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    $rendered = (new OrderModifiedMail($new, null))->render();

    // Het bedrag zelf toetsen, niet alleen dat er een link staat: 100 was al
    // betaald, het nieuwe totaal is 121, dus er hoort exact 21,00 open te
    // staan. Zonder deze assertie zou een fout van 21 naar 121 door de test
    // heen glippen.
    expect(round($new->outstandingAmount(), 2))->toBe(21.0)
        ->and($rendered)->toContain('Nieuw product')
        ->and($rendered)->toContain(CurrencyHelper::formatPrice(21.0))
        ->and($rendered)->toContain('/pay/order/' . $new->hash . '/remainder');
});

it('hangt de factuur van de vervangende order aan de wijzigingsmail', function () {
    // OrderModificationService slaat SendInvoiceJob bewust over, dus zonder
    // deze bijlage krijgt de klant wel bericht dat er iets veranderd is, maar
    // nooit de bijbehorende nieuwe factuur.
    Storage::fake('dashed');

    $old = mailableOrder();
    $new = OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    // createInvoice() in de service heeft de PDF net weggeschreven.
    expect(Storage::disk('dashed')->exists($new->invoicePath()))->toBeTrue();

    $attachments = (new OrderModifiedMail($new, null))->build()->attachments;

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]['options']['mime'])->toBe('application/pdf');
});

it('verstuurt de wijzigingsmail ook zonder factuur-pdf', function () {
    // Een ontbrekende PDF mag het enige bericht dat de klant over de wijziging
    // krijgt nooit tegenhouden.
    Storage::fake('dashed');

    $old = mailableOrder();
    $new = OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    Storage::disk('dashed')->delete($new->invoicePath());

    $mail = (new OrderModifiedMail($new, null))->build();

    expect($mail->attachments)->toHaveCount(0)
        ->and($mail->subject)->toContain('is aangepast');
});

it('stuurt ook een wijzigingsmail wanneer een order in plaats aangepast wordt', function () {
    Mail::fake();
    $order = mailableConceptOrder();

    OrderModificationService::applyInPlace($order, [mailLine(121.0)]);

    Mail::assertSent(OrderModifiedMail::class, fn ($mail) => $mail->hasTo('klant@example.com'));
});

it('gebruikt de sjabloonvertaling van de locale van de order voor het onderwerp', function () {
    // Zelfde seed-idioom als ProformaCheckoutMailTemplateTest/OrderNoteMailTest:
    // een echte EmailTemplate-rij met per-locale subject/blocks, zodat build()
    // via de HasEmailTemplate-tak rendert i.p.v. de kale fallback-view.
    $template = EmailTemplate::firstOrCreate(
        ['mailable_key' => OrderModifiedMail::emailTemplateKey()],
        ['name' => OrderModifiedMail::emailTemplateName(), 'is_active' => true],
    );
    $template->setTranslations('subject', [
        'nl' => 'Je bestelling is aangepast (NL)',
        'en' => 'Your order has been modified (EN)',
    ]);
    $template->setTranslations('from_name', ['nl' => 'Test', 'en' => 'Test']);
    $template->setTranslations('blocks', [
        'nl' => OrderModifiedMail::defaultBlocks(),
        'en' => OrderModifiedMail::defaultBlocks(),
    ]);
    $template->save();

    $order = mailableOrder();
    $order->locale = 'en';
    $order->save();

    $mail = (new OrderModifiedMail($order))->build();

    expect($mail->subject)->toBe('Your order has been modified (EN)');
});
