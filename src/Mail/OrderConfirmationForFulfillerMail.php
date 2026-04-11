<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class OrderConfirmationForFulfillerMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public Order $order;

    public array $orderProducts;

    public bool $sendProductsToCustomer;

    public null|array|Collection $files;

    public function __construct(Order $order, array $orderProducts, bool $sendProductsToCustomer, null|array|Collection $files)
    {
        $this->order = $order;
        $this->orderProducts = $orderProducts;
        $this->sendProductsToCustomer = $sendProductsToCustomer;
        $this->files = $files;
    }

    public static function emailTemplateName(): string
    {
        return 'Orderbevestiging (fulfiller)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar een fulfiller met de producten die zij moeten verzenden.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Bestelling #:orderId: vanuit :siteName:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nieuwe order voor fulfillment #:orderId:', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Onderstaande order dient verzonden te worden door :siteName:.</p>']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => false]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-address', 'data' => ['type' => 'shipping']],
            ['type' => 'order-note', 'data' => []],
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
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->isPaid()->latest()->first() ?? Order::query()->latest()->first();

        return $order ? new self($order, [], true, null) : null;
    }

    public function build()
    {
        $context = [
            'order' => $this->order,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('order-confirmation-for-fulfiller-email-subject', 'orders', 'Bestelling #:orderId: vanuit :siteName:', 'text', [
                    'orderId' => $this->order->invoice_id,
                    'siteName' => Customsetting::get('site_name'),
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));
            $mail = $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.confirm-order-for-fulfiller')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.confirm-order-for-fulfiller'
                : 'dashed-ecommerce-core::emails.confirm-order-for-fulfiller';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('order-confirmation-for-fulfiller-email-subject', 'orders', 'Bestelling #:orderId: vanuit :siteName:', 'text', [
                    'orderId' => $this->order->invoice_id,
                    'siteName' => Customsetting::get('site_name'),
                ]))
                ->with([
                    'order' => $this->order,
                    'orderProducts' => $this->orderProducts,
                    'sendProductsToCustomer' => $this->sendProductsToCustomer,
                    'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                ]);
        }

        foreach ($this->files ?: [] as $file) {
            $media = mediaHelper()->getSingleMedia($file);
            if ($media) {
                $mail->attachFromStorageDisk('dashed', $media->path);
            }
        }

        return $mail;
    }
}
