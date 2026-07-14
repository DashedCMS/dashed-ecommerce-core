<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;

/**
 * Borgt dat de tekst van een bestellings-notitie daadwerkelijk in de mail
 * terechtkomt zodra er een (door de admin geseede) email-template bestaat.
 * Faalde eerder omdat build() de noteContent-variabele vulde vanuit een
 * niet-bestaande kolom (email_content) i.p.v. de note-kolom.
 */
it('includes the note text when rendered through the email template', function () {
    $locale = app()->getLocale();

    $template = EmailTemplate::firstOrCreate(
        ['mailable_key' => OrderNoteMail::emailTemplateKey()],
        ['name' => OrderNoteMail::emailTemplateName(), 'is_active' => true],
    );
    $template->setTranslations('subject', [$locale => OrderNoteMail::defaultSubject()]);
    $template->setTranslations('from_name', [$locale => 'Test']);
    $template->setTranslations('blocks', [$locale => OrderNoteMail::defaultBlocks()]);
    $template->save();

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'first_name' => 'Jan',
        'total' => 121.0,
        'btw' => 21.0,
    ]);

    $orderLog = new OrderLog();
    $orderLog->order_id = $order->id;
    $orderLog->tag = 'order.note.created';
    $orderLog->note = 'Uw pakket vertrekt morgen met PostNL';
    $orderLog->public_for_customer = 1;
    $orderLog->send_email_to_customer = 1;
    $orderLog->email_subject = 'Update over je bestelling';
    $orderLog->images = [];
    $orderLog->save();

    $html = (new OrderNoteMail($order, $orderLog))->render();

    expect($html)->toContain('Uw pakket vertrekt morgen met PostNL');
});

/**
 * Borgt dat het door de admin ingevoerde onderwerp (email_subject op de order-log)
 * voorrang krijgt op het statische template-onderwerp. Faalde eerder omdat build()
 * altijd het template-onderwerp gebruikte zodra er een template bestond.
 */
it('uses the note email_subject as the mail subject over the template subject', function () {
    $locale = app()->getLocale();

    $template = EmailTemplate::firstOrCreate(
        ['mailable_key' => OrderNoteMail::emailTemplateKey()],
        ['name' => OrderNoteMail::emailTemplateName(), 'is_active' => true],
    );
    $template->setTranslations('subject', [$locale => OrderNoteMail::defaultSubject()]);
    $template->setTranslations('from_name', [$locale => 'Test']);
    $template->setTranslations('blocks', [$locale => OrderNoteMail::defaultBlocks()]);
    $template->save();

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'first_name' => 'Jan',
        'total' => 121.0,
        'btw' => 21.0,
    ]);

    $orderLog = new OrderLog();
    $orderLog->order_id = $order->id;
    $orderLog->tag = 'order.note.created';
    $orderLog->note = 'Een korte mededeling';
    $orderLog->public_for_customer = 1;
    $orderLog->send_email_to_customer = 1;
    $orderLog->email_subject = 'Je bestelling is verzonden vandaag';
    $orderLog->images = [];
    $orderLog->save();

    $subject = (new OrderNoteMail($order, $orderLog))->build()->subject;

    expect($subject)->toBe('Je bestelling is verzonden vandaag');
});
