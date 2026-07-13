<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * Handmatig bericht aan de klant vanaf de retour-detailpagina. De template levert
 * de huisstijl en het standaard-onderwerp; onderwerp en :message: zijn per verzending
 * aanpasbaar in het admin-panel.
 */
class OrderReturnCustomMail extends OrderReturnBaseMail
{
    public function __construct(OrderReturn $orderReturn, string $message = '', ?string $subject = null)
    {
        parent::__construct($orderReturn);

        $this->messageBody = $message;
        $this->subjectOverride = $subject;
    }

    public static function emailTemplateName(): string
    {
        return 'Retour: bericht aan klant';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Basis voor handmatige berichten aan de klant vanaf de retourpagina. Het :message:-blok wordt per verzending gevuld.';
    }

    public static function defaultSubject(): string
    {
        return 'Een bericht over je retour voor bestelling :orderNumber:';
    }

    public static function defaultMessage(): string
    {
        return '<p>Beste :firstName:,</p><p>We willen je graag een update geven over je retourverzoek.</p>';
    }

    public static function usableVariablesHint(): string
    {
        return collect(static::availableVariables())
            ->reject(fn ($variable) => $variable === 'message')
            ->map(fn ($variable) => ':' . $variable . ':')
            ->join(', ');
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Een bericht over je retour', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p>']],
            ['type' => 'text', 'data' => ['body' => ':message:']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'button', 'data' => ['label' => 'Bekijk en reageer op je retour', 'url' => ':returnStatusUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
