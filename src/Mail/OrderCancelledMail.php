<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class OrderCancelledMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public static function emailTemplateName(): string
    {
        return 'Order geannuleerd (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant als een bestelling wordt geannuleerd.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Je bestelling #:orderId: is geannuleerd';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bestelling geannuleerd', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>Je bestelling #:orderId: is geannuleerd. Hieronder zie je de details van de geannuleerde bestelling.</p>']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Heb je vragen? Neem gerust contact met ons op.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderId' => $order?->parentCreditOrder?->invoice_id ?? $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->whereNotNull('parent_credit_order_id')->latest()->first() ?? Order::query()->latest()->first();

        return $order ? new self($order) : null;
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'orderId' => $this->order->parentCreditOrder?->invoice_id ?? $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('order-cancelled-email-subject', 'orders', 'Je bestelling #:orderId: is geannuleerd', 'text', [
                    'orderId' => $context['orderId'],
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.cancelled-order')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.cancelled-order'
            : 'dashed-ecommerce-core::emails.cancelled-order';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('order-cancelled-email-subject', 'orders', 'Je bestelling #:orderId: is geannuleerd', 'text', [
                'orderId' => $context['orderId'],
            ]))
            ->with([
                'order' => $this->order,
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
            ]);
    }
}
