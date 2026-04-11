<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentLinkMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Order $order;

    public float $amount;

    public string $paymentUrl;

    public function __construct(Order $order, float $amount, string $paymentUrl)
    {
        $this->order = $order;
        $this->amount = $amount;
        $this->paymentUrl = $paymentUrl;
    }

    public static function emailTemplateName(): string
    {
        return 'Betaallink (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant met een link om het openstaande bedrag te voldoen.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'amountFormatted', 'paymentUrl', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Betaallink voor bestelling #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Betaallink voor je bestelling', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>Er staat nog een bedrag open voor je bestelling #:orderId:. Je kunt deze via onderstaande knop veilig online voldoen.</p><p><strong>Openstaand bedrag:</strong> :amountFormatted:</p>']],
            ['type' => 'button', 'data' => ['label' => 'Betaal je bestelling', 'url' => ':paymentUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Heb je vragen over deze betaling? Neem gerust contact met ons op.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->isPaid()->latest()->first() ?? Order::query()->latest()->first();
        $amount = $order?->outstandingAmount() ?: ($order?->total ?? 99.95);

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'amountFormatted' => CurrencyHelper::formatPrice($amount),
            'paymentUrl' => $order ? url('/pay/order/' . $order->hash . '/remainder') : url('/pay/demo'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->latest()->first();
        if (! $order) {
            return null;
        }

        $amount = $order->outstandingAmount() ?: $order->total;

        return new self($order, (float) $amount, url('/pay/order/' . $order->hash . '/remainder'));
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'amountFormatted' => CurrencyHelper::formatPrice($this->amount),
            'paymentUrl' => $this->paymentUrl,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);
        $fallbackSubject = Translation::get('payment-link-email-subject', 'orders', 'Betaallink voor bestelling #:orderId:', 'text', [
            'orderId' => $this->order->invoice_id,
        ]);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($fallbackSubject, $context));
        }

        return $this->html(
            '<p>Beste ' . e($this->order->first_name) . ',</p>'
            . '<p>Er staat nog een bedrag open van <strong>' . CurrencyHelper::formatPrice($this->amount) . '</strong> voor bestelling #' . e($this->order->invoice_id) . '.</p>'
            . '<p><a href="' . e($this->paymentUrl) . '">Betaal hier je bestelling</a></p>'
            . '<p>Met vriendelijke groet,<br>' . e(Customsetting::get('site_name', Sites::getActive(), '')) . '</p>'
        )
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($fallbackSubject);
    }
}
