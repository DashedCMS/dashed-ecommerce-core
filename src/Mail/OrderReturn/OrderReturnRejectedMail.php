<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

class OrderReturnRejectedMail extends OrderReturnBaseMail
{
    public static function emailTemplateName(): string
    {
        return 'Retour: afgekeurd';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant wanneer een retourverzoek is afgewezen.';
    }

    public static function defaultSubject(): string
    {
        return 'Je retour is afgewezen voor bestelling :orderNumber:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Retour afgewezen', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>Helaas kunnen we je retourverzoek voor bestelling <strong>:orderNumber:</strong> niet accepteren.</p><p><strong>Reden:</strong> :rejectedReason:</p><p>Heb je vragen over deze beslissing? Neem dan contact met ons op.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p><a href=":returnStatusUrl:">De status van je retour volgen</a></p>']],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
