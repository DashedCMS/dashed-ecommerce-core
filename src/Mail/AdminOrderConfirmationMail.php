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
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class AdminOrderConfirmationMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
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
        return 'Orderbevestiging (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder zodra een order is geplaatst.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'totalFormatted', 'siteName', 'orderUrl', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Nieuwe bestelling ontvangen #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nieuwe bestelling #:orderId:', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Er is een nieuwe bestelling geplaatst op de webshop.</p>']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-methods', 'data' => ['show_shipping' => true, 'show_payment' => true, 'show_instructions' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-address', 'data' => ['type' => 'shipping']],
            ['type' => 'order-address', 'data' => ['type' => 'invoice']],
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
                Translation::get('admin-order-confirmation-email-subject', 'orders', 'Nieuwe bestelling ontvangen #:orderId:', 'text', [
                    'orderId' => $this->order->invoice_id,
                ]),
                $context
            );

            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name')
            );

            $mail = $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($subject);
        } else {
            $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.admin-confirm-order')
                ? config('dashed-core.site_theme', 'dashed') . '.emails.admin-confirm-order'
                : 'dashed-ecommerce-core::emails.admin-confirm-order';

            $mail = $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject(Translation::get('admin-order-confirmation-email-subject', 'orders', 'Nieuwe bestelling ontvangen #:orderId:', 'text', [
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

        return $mail;
    }

    public function telegramSummary(): TelegramSummary
    {
        $orderProducts = ($this->order->orderProducts ?? collect())->filter(fn ($op) => ! empty($op->product_id));
        $productList = $orderProducts
            ->map(fn ($op) => '• ' . (int) $op->quantity . 'x ' . ($op->name ?? '-'))
            ->implode("\n");

        return new TelegramSummary(
            title: 'Nieuwe bestelling #' . $this->order->invoice_id,
            fields: [
                'Klant' => trim(($this->order->first_name ?? '') . ' ' . ($this->order->last_name ?? '')) ?: ($this->order->email ?? '-'),
                'Bedrag' => '€' . number_format((float) $this->order->total, 2, ',', '.'),
                'Items' => (string) ((int) $orderProducts->sum('quantity')) . ' producten',
                'Producten' => $productList ?: null,
                'Betaalmethode' => $this->order->payment_method ?? null,
            ],
            adminUrl: rescue(fn () => route('filament.dashed.resources.orders.edit', ['record' => $this->order->id]), null, false),
            emoji: '🛒',
        );
    }
}
