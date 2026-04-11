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

class AdminOrderCancelledMail extends Mailable implements RegistersEmailTemplate
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
        return 'Order geannuleerd (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder als een order wordt geannuleerd.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Bestelling #:orderId: geannuleerd';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bestelling #:orderId: geannuleerd', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Een bestelling is geannuleerd. Hieronder de details.</p>']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
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
                Translation::get('admin-order-cancelled-email-subject', 'orders', 'Bestelling #:orderId: geannuleerd', 'text', [
                    'orderId' => $context['orderId'],
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.admin-cancelled-order')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.admin-cancelled-order'
                : 'dashed-ecommerce-core::emails.admin-cancelled-order';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('admin-order-cancelled-email-subject', 'orders', 'Bestelling #:orderId: geannuleerd', 'text', [
                    'orderId' => $context['orderId'],
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

        return $mail;
    }
}
