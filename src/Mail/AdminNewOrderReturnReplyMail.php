<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class AdminNewOrderReturnReplyMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public OrderReturn $orderReturn, public string $customerMessage = '')
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Retour: klant reageerde (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder zodra een klant reageert in de retour-thread.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'customerMessage', 'siteName', 'primaryColor', 'adminReturnUrl'];
    }

    public static function defaultSubject(): string
    {
        return 'Nieuwe reactie op retourverzoek voor bestelling #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Nieuwe reactie op retourverzoek #:orderId:', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>:customerFirstName: :customerLastName: heeft gereageerd op het retourverzoek voor bestelling <strong>#:orderId:</strong>.</p>']],
            ['type' => 'text', 'data' => ['body' => '<p><strong>Bericht van de klant:</strong></p><p>:customerMessage:</p>']],
            ['type' => 'divider', 'data' => []],
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
            'customerMessage' => 'Wanneer krijg ik mijn geld terug?',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $return = OrderReturn::query()->latest()->first();

        return $return ? new self($return, 'Voorbeeldreactie van de klant.') : null;
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
            'customerMessage' => e($this->customerMessage),
            'siteName' => Customsetting::get('site_name'),
            'adminReturnUrl' => rescue(fn () => route('filament.dashed.resources.order-returns.view', ['record' => $this->orderReturn->id]), '', false),
        ];

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(static::defaultSubject(), $context);
            [$fromEmail, $fromName] = $this->templateFrom(Customsetting::get('site_from_email'), Customsetting::get('site_name'));

            return $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.notification')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.notification'
            : 'dashed-core::emails.notification';

        return $this->view($view)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(str_replace(':orderId:', (string) $order?->invoice_id, static::defaultSubject()))
            ->with([
                'logo' => Customsetting::get('site_logo', Sites::getActive(), ''),
                'notification' => 'Er is een nieuwe reactie op een retourverzoek.',
            ]);
    }

    public function telegramSummary(): TelegramSummary
    {
        $order = $this->orderReturn->order;

        return new TelegramSummary(
            title: 'Nieuwe retour-reactie #' . ($order?->invoice_id ?? $this->orderReturn->id),
            fields: [
                'Klant' => trim(($order?->first_name ?? '') . ' ' . ($order?->last_name ?? '')) ?: ($this->orderReturn->email ?? '-'),
                'Bericht' => $this->customerMessage ?: '-',
            ],
            adminUrl: rescue(fn () => route('filament.dashed.resources.order-returns.view', ['record' => $this->orderReturn->id]), null, false),
            emoji: '💬',
        );
    }
}
