<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class ProformaCheckoutMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order, public string $checkoutUrl)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Proforma afrekenen (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant met een link om de proforma-bestelling af te rekenen.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'checkoutUrl', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Rond je bestelling af bij :siteName:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Jouw bestelling staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>Jouw bestelling is aangemaakt en staat klaar om te worden afgerekend. Klik op de knop hieronder om de betaling te voltooien.</p>']],
            ['type' => 'button', 'data' => ['label' => 'Bestelling afrekenen', 'url' => ':checkoutUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Heb je vragen over je bestelling? Neem gerust contact met ons op.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->isProforma()->latest()->first() ?? Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'PROFORMA-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'checkoutUrl' => $order ? url('/proforma/' . $order->hash) : url('/proforma/demo'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->isProforma()->latest()->first() ?? Order::query()->latest()->first();
        if (! $order) {
            return null;
        }

        return new self($order, url('/proforma/' . $order->hash));
    }

    public function build()
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), '');
        $primaryColor = Customsetting::get('mail_primary_color', Sites::getActive(), '') ?: '#A0131C';

        $context = [
            'order' => $this->order,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'checkoutUrl' => $this->checkoutUrl,
            'siteName' => $siteName,
            'primaryColor' => $primaryColor,
        ];

        $templateHtml = $this->renderFromTemplate($context);
        $fallbackSubject = 'Rond je bestelling af bij ' . $siteName;

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($fallbackSubject, $context));
        }

        return $this->html(
            '<p>Beste ' . e($this->order->first_name) . ',</p>'
            . '<p>Jouw bestelling staat klaar om te worden afgerekend.</p>'
            . '<p><a href="' . e($this->checkoutUrl) . '">' . e($this->checkoutUrl) . '</a></p>'
            . '<p>Met vriendelijke groet,<br>' . e($siteName) . '</p>'
        )
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($fallbackSubject);
    }
}
