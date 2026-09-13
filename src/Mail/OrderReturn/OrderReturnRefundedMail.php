<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

class OrderReturnRefundedMail extends OrderReturnBaseMail
{
    public static function emailTemplateName(): string
    {
        return 'Retour: terugbetaald';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra de terugbetaling van een retour is geregistreerd.';
    }

    public static function defaultSubject(): string
    {
        return 'Je terugbetaling voor bestelling :orderNumber: is onderweg';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Terugbetaling onderweg', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>We hebben <strong>:refundAmount:</strong> aan je teruggestort via :refundMethod:, voor je retour van bestelling <strong>:orderNumber:</strong>. Afhankelijk van je bank kan het een paar werkdagen duren voor je het ziet.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
