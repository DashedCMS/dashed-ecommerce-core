<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;

class AdminPaymentStartFailedMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public string $exceptionClass;
    public string $exceptionMessage;
    public string $exceptionLocation;

    public function __construct(
        public Order $order,
        public OrderPayment $orderPayment,
        Throwable $exception,
        public string $context = 'general',
    ) {
        $this->exceptionClass = $exception::class;
        $this->exceptionMessage = $exception->getMessage();
        $this->exceptionLocation = basename($exception->getFile()) . ':' . $exception->getLine();
    }

    public static function emailTemplateName(): string
    {
        return 'Betaling kon niet gestart worden (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder zodra een poging om een betaling te starten faalt (checkout, POS, betaallink, restbetaling of handmatige order).';
    }

    public static function availableVariables(): array
    {
        return [
            'orderId',
            'context',
            'psp',
            'paymentMethod',
            'amount',
            'customerName',
            'customerEmail',
            'exceptionClass',
            'exceptionMessage',
            'exceptionLocation',
            'orderUrl',
            'siteName',
        ];
    }

    public static function defaultSubject(): string
    {
        return 'Betaling kon niet gestart worden voor order #:orderId:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Betaling kon niet gestart worden', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Bij order <strong>#:orderId:</strong> kon de betaling niet worden gestart via :psp:.</p><p><strong>Context:</strong> :context:<br><strong>Betaalmethode:</strong> :paymentMethod:<br><strong>Bedrag:</strong> :amount:<br><strong>Klant:</strong> :customerName: (:customerEmail:)</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p><strong>Foutmelding:</strong></p><pre style="background:#f4f4f4;padding:12px;border-radius:6px;font-family:monospace;white-space:pre-wrap;">:exceptionClass:<br>:exceptionMessage:<br><br>in :exceptionLocation:</pre>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->latest()->first();

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'context' => 'checkout',
            'psp' => 'mollie',
            'paymentMethod' => 'iDEAL',
            'amount' => '€ 49,95',
            'customerName' => $order?->name ?? 'Jan Jansen',
            'customerEmail' => $order?->email ?? 'klant@example.com',
            'exceptionClass' => 'Mollie\\Api\\Exceptions\\ApiException',
            'exceptionMessage' => 'Authentication failed: invalid API key',
            'exceptionLocation' => 'Mollie.php:142',
            'orderUrl' => $order && method_exists($order, 'getUrl') ? $order->getUrl() : '#',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->latest()->first();
        $orderPayment = $order?->orderPayments()->latest()->first();
        if (! $order || ! $orderPayment) {
            return null;
        }

        return new self(
            $order,
            $orderPayment,
            new \RuntimeException('Authentication failed: invalid API key'),
            'checkout',
        );
    }

    public function build()
    {
        $context = $this->mailContext();

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                Translation::get(
                    'admin-payment-start-failed-email-subject',
                    'orders',
                    'Betaling kon niet gestart worden voor order #:orderId:',
                    'text',
                    ['orderId' => $this->order->invoice_id]
                ),
                $context
            );

            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name')
            );

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($subject);
        }

        return $this
            ->view('dashed-ecommerce-core::emails.admin-payment-start-failed', $context)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject(Translation::get(
                'admin-payment-start-failed-email-subject',
                'orders',
                'Betaling kon niet gestart worden voor order #:orderId:',
                'text',
                ['orderId' => $this->order->invoice_id]
            ));
    }

    public function telegramSummary(): TelegramSummary
    {
        $fields = [
            'Order' => '#' . ($this->order->invoice_id ?? $this->order->id),
            'Context' => $this->context,
            'PSP' => $this->orderPayment->psp,
            'Betaalmethode' => $this->orderPayment->payment_method ?? '-',
            'Bedrag' => '€ ' . number_format((float) $this->orderPayment->amount, 2, ',', '.'),
            'Klant' => trim(($this->order->first_name ?? '') . ' ' . ($this->order->last_name ?? '')) ?: ($this->order->name ?? '-'),
            'E-mail' => $this->order->email ?? '-',
            'Foutklasse' => $this->exceptionClass,
            'Foutmelding' => $this->exceptionMessage,
            'Locatie' => $this->exceptionLocation,
        ];

        return new TelegramSummary(
            title: 'Betaling kon niet gestart worden',
            fields: $fields,
            adminUrl: method_exists($this->order, 'getUrl') ? $this->order->getUrl() : null,
            emoji: '⚠️',
            linkLabel: 'Bekijk order in CMS',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mailContext(): array
    {
        return [
            'order' => $this->order,
            'orderPayment' => $this->orderPayment,
            'orderId' => $this->order->invoice_id,
            'context' => $this->context,
            'psp' => $this->orderPayment->psp,
            'paymentMethod' => $this->orderPayment->payment_method ?? '-',
            'amount' => '€ ' . number_format((float) $this->orderPayment->amount, 2, ',', '.'),
            'customerName' => trim(($this->order->first_name ?? '') . ' ' . ($this->order->last_name ?? '')) ?: ($this->order->name ?? '-'),
            'customerEmail' => $this->order->email ?? '-',
            'exceptionClass' => $this->exceptionClass,
            'exceptionMessage' => $this->exceptionMessage,
            'exceptionLocation' => $this->exceptionLocation,
            'orderUrl' => method_exists($this->order, 'getUrl') ? $this->order->getUrl() : '#',
            'siteName' => Customsetting::get('site_name'),
        ];
    }
}
