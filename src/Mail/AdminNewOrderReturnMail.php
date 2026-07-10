<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class AdminNewOrderReturnMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public OrderReturn $orderReturn;

    public function __construct(OrderReturn $orderReturn)
    {
        $this->orderReturn = $orderReturn;
    }

    public static function emailTemplateName(): string
    {
        return 'Nieuw retourverzoek (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder zodra een klant een nieuw retourverzoek indient.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'returnRequestedAt', 'returnReason', 'returnLines', 'siteName', 'primaryColor', 'adminReturnUrl'];
    }

    public static function defaultSubject(): string
    {
        return 'Nieuw retourverzoek voor bestelling #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nieuw retourverzoek voor bestelling #:orderId:', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Er is een nieuw retourverzoek binnengekomen voor bestelling <strong>#:orderId:</strong> van :customerFirstName: :customerLastName:.</p><p>Aangevraagd op :returnRequestedAt:.</p>']],
            ['type' => 'text', 'data' => ['body' => '<p><strong>Geretourneerde producten:</strong></p><p>:returnLines:</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'button', 'data' => ['label' => 'Bekijk retourverzoek in CMS', 'url' => ':adminReturnUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
        ];
    }

    public static function sampleData(): array
    {
        $return = OrderReturn::query()->latest()->first();
        $order = $return?->order;

        return [
            'order' => $order,
            'orderReturn' => $return,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'returnRequestedAt' => optional($return?->requested_at)->format('d-m-Y H:i') ?? '01-01-2026 12:00',
            'returnReason' => $return?->customer_note ?? 'Past niet',
            'returnLines' => $return ? self::returnLinesHtmlFor($return) : '2x Voorbeeldproduct',
            'siteName' => Customsetting::get('site_name'),
            'adminReturnUrl' => rescue(fn () => route('filament.dashed.resources.order-returns.view', ['record' => $return?->id]), '', false),
        ];
    }

    protected static function returnLinesHtmlFor(OrderReturn $orderReturn): string
    {
        return $orderReturn->lines
            ->map(fn ($line) => e($line->quantity . 'x ' . ($line->orderProduct?->name ?? '')))
            ->implode('<br>');
    }

    public static function makeForTest(): ?self
    {
        $return = OrderReturn::query()->latest()->first();

        return $return ? new self($return) : null;
    }

    public function build()
    {
        $order = $this->orderReturn->order;

        $context = [
            'order' => $order,
            'orderReturn' => $this->orderReturn,
            'orderId' => $order?->invoice_id,
            'customerFirstName' => $order?->first_name,
            'customerLastName' => $order?->last_name,
            'returnRequestedAt' => optional($this->orderReturn->requested_at)->format('d-m-Y H:i') ?? '',
            'returnReason' => (string) $this->orderReturn->customer_note,
            'returnLines' => self::returnLinesHtmlFor($this->orderReturn),
            'siteName' => Customsetting::get('site_name'),
            'adminReturnUrl' => rescue(fn () => route('filament.dashed.resources.order-returns.view', ['record' => $this->orderReturn->id]), '', false),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get('admin-new-order-return-email-subject', 'orders', 'Nieuw retourverzoek voor bestelling #:orderId:', 'text', [
                    'orderId' => $context['orderId'],
                ]),
                $context
            );
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.notification')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.notification'
            : 'dashed-core::emails.notification';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get('admin-new-order-return-email-subject', 'orders', 'Nieuw retourverzoek voor bestelling #:orderId:', 'text', [
                'orderId' => $context['orderId'],
            ]))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'notification' => 'Er is een nieuw retourverzoek binnengekomen voor bestelling #' . $context['orderId'] . '.',
            ]);
    }

    public function telegramSummary(): TelegramSummary
    {
        $order = $this->orderReturn->order;

        return new TelegramSummary(
            title: 'Nieuw retourverzoek #' . ($order?->invoice_id ?? $this->orderReturn->id),
            fields: [
                'Klant' => trim(($order?->first_name ?? '') . ' ' . ($order?->last_name ?? '')) ?: ($this->orderReturn->email ?? '-'),
                'Reden' => $this->orderReturn->customer_note ?: '-',
            ],
            adminUrl: rescue(fn () => route('filament.dashed.resources.order-returns.index'), null, false),
            emoji: '📦',
        );
    }
}
