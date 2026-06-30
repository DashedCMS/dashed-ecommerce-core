<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;

/**
 * Borgt dat de proforma-mail, zodra er een (door de admin geseede) email-template
 * bestaat, de gedeelde nette layout gebruikt EN de producten + prijzen toont.
 * Dit faalt als de mailable niet geregistreerd is of de order-summary ontbreekt,
 * want dan valt build() terug op de kale HTML zonder producten.
 */
it('renders the proforma mail in the shared layout with product lines when a template exists', function () {
    $locale = app()->getLocale();

    // Seed de template-rij zoals de admin (ListEmailTemplates) dat doet.
    $template = EmailTemplate::firstOrCreate(
        ['mailable_key' => ProformaCheckoutMail::emailTemplateKey()],
        ['name' => ProformaCheckoutMail::emailTemplateName(), 'is_active' => true],
    );
    $template->setTranslations('subject', [$locale => ProformaCheckoutMail::defaultSubject()]);
    $template->setTranslations('from_name', [$locale => 'Test']);
    $template->setTranslations('blocks', [$locale => ProformaCheckoutMail::defaultBlocks()]);
    $template->save();

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'is_proforma' => true,
        'first_name' => 'Jan',
        'total' => 121.0,
        'btw' => 21.0,
    ]);
    $order->orderProducts()->create([
        'product_id' => null,
        'name' => 'Maatwerk dienst',
        'quantity' => 1,
        'price' => 121.0,
        'vat_rate' => 21,
    ]);

    $html = (new ProformaCheckoutMail($order, url('/proforma/' . $order->hash)))->render();

    expect($html)->toContain('Maatwerk dienst')               // product staat in de mail
        ->and($html)->toContain('/proforma/' . $order->hash); // afrekenlink staat in de mail
});
