<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

class OrderReturnRequestedMail extends OrderReturnBaseMail
{
    public static function emailTemplateName(): string
    {
        return 'Retour: aanvraag ontvangen';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra een retourverzoek is ingediend. Wettelijk verplichte bevestiging van de herroeping (vermeldt bestelnummer en tijdstip).';
    }

    public static function defaultSubject(): string
    {
        return 'We hebben je retourverzoek ontvangen voor bestelling :orderNumber:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Retourverzoek ontvangen', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>We hebben je retourverzoek voor bestelling <strong>:orderNumber:</strong> ontvangen op <strong>:returnRequestedAt:</strong>.</p><p>We beoordelen je verzoek en je ontvangt zo snel mogelijk bericht of het is goedgekeurd of afgewezen.</p>']],
            ['type' => 'text', 'data' => ['body' => '<p>Je hebt de volgende producten aangemeld voor retour:</p><p>:returnLines:</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
