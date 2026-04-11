<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class PreOrderConfirmationMail extends Mailable implements RegistersEmailTemplate
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
        return 'Pre-order bevestiging (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra een pre-order is geplaatst.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'totalFormatted', 'siteName', 'orderUrl', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Bevestiging van je pre-order #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bedankt voor je pre-order, :customerFirstName:!', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>We hebben je pre-order goed ontvangen. Zodra we de producten binnen hebben, verzenden we deze naar je toe.</p>']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'button', 'data' => ['label' => 'Bekijk je bestelling online', 'url' => ':orderUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-methods', 'data' => ['show_shipping' => true, 'show_payment' => true, 'show_instructions' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-address', 'data' => ['type' => 'shipping']],
            ['type' => 'order-address', 'data' => ['type' => 'invoice']],
            ['type' => 'order-note', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Heb je vragen? Neem gerust contact met ons op.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->isPaid()->latest()->first() ?? Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'totalFormatted' => $order?->total ?? '€ 99,95',
            'siteName' => Customsetting::get('site_name'),
            'orderUrl' => $order && method_exists($order, 'getUrl') ? $order->getUrl() : '#',
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->isPaid()->latest()->first() ?? Order::query()->latest()->first();

        return $order ? new self($order) : null;
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'totalFormatted' => $this->order->total ?? '',
            'siteName' => Customsetting::get('site_name'),
            'orderUrl' => method_exists($this->order, 'getUrl') ? $this->order->getUrl() : '#',
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('pre-order-confirmation-email-subject', 'pre-orders', 'Bevestiging van je pre-order #:orderId:', 'text', [
                    'orderId' => $this->order->invoice_id,
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.confirm-pre-order')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.confirm-pre-order'
                : 'dashed-ecommerce-core::emails.confirm-pre-order';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('pre-order-confirmation-email-subject', 'pre-orders', 'Bevestiging van je pre-order #:orderId:', 'text', [
                    'orderId' => $this->order->invoice_id,
                ]))
                ->with([
                    'order' => $this->order,
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        $invoicePath = Storage::disk('dashed')->url('dashed/invoices/invoice-' . $this->order->invoice_id . '-' . $this->order->hash . '.pdf');
        $mail->attach($invoicePath, [
            'as' => Customsetting::get('site_name') . ' - ' . $this->order->invoice_id . '.pdf',
            'mime' => 'application/pdf',
        ]);

        $bccEmail = Customsetting::get('checkout_bcc_email', $this->order->site_id);
        if ($bccEmail) {
            $mail->bcc($bccEmail);
        }

        return $mail;
    }
}
