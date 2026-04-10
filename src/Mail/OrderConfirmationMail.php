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

class OrderConfirmationMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public static function emailTemplateKey(): string
    {
        return self::class;
    }

    public static function emailTemplateName(): string
    {
        return 'Orderbevestiging (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra een order is geplaatst.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'totalFormatted', 'siteName'];
    }

    public static function availableBlockKeys(): array
    {
        return ['heading', 'text', 'button', 'image', 'divider', 'order-summary'];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'totalFormatted' => $order?->total ?? '€ 99,95',
            'siteName' => Customsetting::get('site_name'),
        ];
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
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('order-confirmation-email-subject', 'orders', 'Order confirmation for order #:orderId:', 'text', [
                    'orderId' => $this->order->invoice_id,
                ])
            );

            $mail = $this->html($templateHtml)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.confirm-order')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.confirm-order'
                : 'dashed-ecommerce-core::emails.confirm-order';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('order-confirmation-email-subject', 'orders', 'Order confirmation for order #:orderId:', 'text', [
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
