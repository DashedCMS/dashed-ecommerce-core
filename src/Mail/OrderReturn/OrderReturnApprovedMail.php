<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

class OrderReturnApprovedMail extends OrderReturnBaseMail
{
    public static function emailTemplateName(): string
    {
        return 'Retour: goedgekeurd';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant wanneer een retourverzoek is goedgekeurd.';
    }

    public static function defaultSubject(): string
    {
        return 'Je retour is goedgekeurd (:invoiceId:)';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Retour goedgekeurd', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>Goed nieuws: je retourverzoek voor bestelling <strong>:invoiceId:</strong> is goedgekeurd.</p><p>:adminNote:</p><p>We verwerken het retour zo spoedig mogelijk. Je ontvangt bericht zodra de terugbetaling of omruiling is afgehandeld.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
