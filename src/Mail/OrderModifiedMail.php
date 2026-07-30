<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class OrderModifiedMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $note = null,
    ) {
    }

    public static function emailTemplateName(): string
    {
        return 'Bestelling gewijzigd (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant zodra hun bestelling is aangepast, met de betaallink als er nog een bedrag openstaat.';
    }

    public static function availableVariables(): array
    {
        return ['orderId', 'customerFirstName', 'customerLastName', 'note', 'outstandingFormatted', 'overpaidFormatted', 'paymentUrl', 'paymentNotice', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Je bestelling #:orderId: is aangepast';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Je bestelling is aangepast', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :customerFirstName:,</p><p>Je bestelling #:orderId: is aangepast. Hieronder staat de nieuwe inhoud.</p>']],
            ['type' => 'text', 'data' => ['body' => ':note:']],
            ['type' => 'order-details', 'data' => []],
            ['type' => 'divider', 'data' => []],
            ['type' => 'order-summary', 'data' => ['show_totals' => true]],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => ':paymentNotice:']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $order = Order::query()->latest()->first();
        $outstandingAmount = $order?->outstandingAmount() ?? 0.0;
        $overpaidAmount = $order?->overpaidAmount() ?? 0.0;

        return [
            'order' => $order,
            'orderId' => $order?->invoice_id ?? 'DEMO-001',
            'customerFirstName' => $order?->first_name ?? 'Jan',
            'customerLastName' => $order?->last_name ?? 'Jansen',
            'note' => 'Het blauwe model was op, we hebben deze vervangen door het zwarte model.',
            'outstandingFormatted' => CurrencyHelper::formatPrice($outstandingAmount),
            'overpaidFormatted' => CurrencyHelper::formatPrice($overpaidAmount),
            'paymentUrl' => $order ? url('/pay/order/' . $order->hash . '/remainder') : url('/pay/demo'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $order = Order::query()->latest()->first();

        return $order ? new self($order) : null;
    }

    public function build()
    {
        $locale = $this->order->locale;

        $outstandingAmount = $this->order->outstandingAmount();
        $overpaidAmount = $this->order->overpaidAmount();
        $paymentUrl = url('/pay/order/' . $this->order->hash . '/remainder');

        $context = [
            'order' => $this->order,
            'orderId' => $this->order->invoice_id,
            'customerFirstName' => $this->order->first_name,
            'customerLastName' => $this->order->last_name,
            'note' => $this->note ?? '',
            'outstandingFormatted' => CurrencyHelper::formatPrice($outstandingAmount),
            'overpaidFormatted' => CurrencyHelper::formatPrice($overpaidAmount),
            'paymentUrl' => $paymentUrl,
            'paymentNotice' => $this->paymentNotice($outstandingAmount, $overpaidAmount, $paymentUrl),
            'siteName' => Customsetting::get('site_name'),
        ];

        $templateHtml = $this->renderFromTemplate($context, $locale);

        if ($templateHtml !== null) {
            $subject = $this->templateSubject(
                'Je bestelling #' . $this->order->invoice_id . ' is aangepast',
                $context,
                $locale
            );

            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name'),
                $locale
            );

            return $this->attachInvoice(
                $this->html($templateHtml)
                    ->from($fromEmail, $fromName)
                    ->subject($subject)
            );
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.order-modified')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.order-modified'
            : 'dashed-ecommerce-core::emails.order-modified';

        return $this->attachInvoice(
            $this->view($view)
                ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
                ->subject('Je bestelling #' . $this->order->invoice_id . ' is aangepast')
                ->with([
                    'order' => $this->order,
                    'note' => $this->note,
                    'outstandingAmount' => $outstandingAmount,
                    'overpaidAmount' => $overpaidAmount,
                    'paymentUrl' => $paymentUrl,
                ])
        );
    }

    /**
     * Hangt de factuur van de (gewijzigde of vervangende) bestelling aan de
     * mail, op dezelfde manier als OrderConfirmationMail dat doet. De klant
     * krijgt anders wel bericht dat zijn bestelling veranderd is, maar nooit de
     * bijbehorende nieuwe factuur: OrderModificationService slaat SendInvoiceJob
     * bewust over.
     *
     * Het bestaan van het bestand wordt eerst gecontroleerd en de attach zelf
     * staat in een try/catch: een ontbrekende of onleesbare PDF mag de
     * wijzigingsmail nooit tegenhouden, want dat is het enige bericht dat de
     * klant over de wijziging krijgt.
     */
    protected function attachInvoice(self $mail): self
    {
        try {
            $invoicePath = $this->order->invoicePath();

            if (! $invoicePath || ! Storage::disk('dashed')->exists($invoicePath)) {
                return $mail;
            }

            $mail->attach(Storage::disk('dashed')->url($invoicePath), [
                'as' => Customsetting::get('site_name') . ' - ' . $this->order->invoice_id . '.pdf',
                'mime' => 'application/pdf',
            ]);
        } catch (\Throwable $e) {
            OrderLog::createLog(
                orderId: $this->order->id,
                tag: 'order.modified.mail.invoice-attach.failed',
                note: 'Error: ' . $e->getMessage(),
            );
        }

        return $mail;
    }

    /**
     * Bouwt de dynamische afsluitzin voor het block-template: een betaalknop
     * bij een openstaand bedrag, de terugbetaalmelding bij te veel betaald,
     * of de bevestiging dat er niets meer hoeft te gebeuren. Net als :message:
     * bij OrderReturnCustomMail wordt dit bewust rauw (niet ge-escaped) HTML
     * ingevoegd; de bedragen zelf worden wel ge-escaped.
     */
    protected function paymentNotice(float $outstandingAmount, float $overpaidAmount, string $paymentUrl): string
    {
        if ($outstandingAmount > 0) {
            return '<p>Er staat nog ' . e(CurrencyHelper::formatPrice($outstandingAmount)) . ' open. Je kunt dat bedrag hier voldoen:</p>'
                . '<p><a href="' . e($paymentUrl) . '">Betaal ' . e(CurrencyHelper::formatPrice($outstandingAmount)) . '</a></p>';
        }

        if ($overpaidAmount > 0) {
            return '<p>Je hebt ' . e(CurrencyHelper::formatPrice($overpaidAmount)) . ' te veel betaald. Dat bedrag storten wij aan je terug.</p>';
        }

        return '<p>Er hoeft niets bijbetaald te worden.</p>';
    }
}
