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
        return 'We hebben je herroeping ontvangen (:invoiceId:)';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Herroeping ontvangen', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>We hebben je herroeping voor bestelling <strong>:invoiceId:</strong> ontvangen op <strong>:returnRequestedAt:</strong>.</p><p>Je aankoop wordt overeenkomstig het herroepingsrecht ongedaan gemaakt. We nemen zo snel mogelijk contact met je op over de verdere afhandeling.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
