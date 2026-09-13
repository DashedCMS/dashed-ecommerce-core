<?php

namespace Dashed\DashedEcommerceCore\Mail\OrderReturn;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;

class OrderReturnProcessedMail extends OrderReturnBaseMail
{
    public static function emailTemplateName(): string
    {
        return 'Retour: verwerkt';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra de retour is verwerkt en de creditfactuur is gemaakt. De terugbetaling volgt apart.';
    }

    public static function defaultSubject(): string
    {
        return 'Je retour voor bestelling :orderNumber: is verwerkt';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Retour verwerkt', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :firstName:,</p><p>We hebben je retour voor bestelling <strong>:orderNumber:</strong> ontvangen en gecontroleerd.</p><p>Gecrediteerd:<br>:creditedLines:</p><p>Het bedrag van <strong>:creditAmount:</strong> ontvang je binnen :refundDays: dagen terug. De creditfactuur vind je in de bijlage.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p><a href=":returnStatusUrl:">De status van je retour volgen</a></p>']],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public function build()
    {
        $mail = parent::build();

        $creditOrder = $this->orderReturn->creditOrder;
        $path = $creditOrder ? ltrim((string) $creditOrder->invoicePath(), '/') : null;
        if ($path && Storage::disk('dashed')->exists($path)) {
            $mail->attachFromStorageDisk('dashed', $path, Customsetting::get('site_name') . ' - ' . $creditOrder->invoice_id . '.pdf', ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
