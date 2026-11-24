<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Notifications\DTOs\TelegramSummary;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedCore\Notifications\Contracts\SendsToTelegram;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource;

/**
 * Sent to the admin when a customer gets stuck in the checkout on an
 * unusual validation error (see CheckoutValidationFailure), at most once
 * per cart per day.
 */
class AdminCheckoutErrorMail extends Mailable implements RegistersEmailTemplate, SendsToTelegram
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public CheckoutAbandonment $abandonment)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Klant loopt vast in de checkout (beheerder)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de beheerder als een klant niet kan afrekenen door een opvallende formulierfout (bijv. een onbekend land of postcodeformaat, of drie keer hetzelfde veld). Hoogstens één keer per winkelwagen per dag.';
    }

    public static function availableVariables(): array
    {
        return ['cartId', 'customerEmail', 'cartTotal', 'attempts', 'errors', 'cartUrl', 'siteName'];
    }

    public static function defaultSubject(): string
    {
        return 'Klant loopt vast in de checkout (winkelwagen #:cartId:)';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Klant loopt vast in de checkout', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Winkelwagen <strong>#:cartId:</strong> van :customerEmail: (:cartTotal:) komt niet door de controle van het afrekenformulier. Pogingen: :attempts:.</p>']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p><strong>Fouten bij de laatste poging:</strong></p><p>:errors:</p><p><a href=":cartUrl:">Bekijk de winkelwagen in het CMS</a></p>']],
        ];
    }

    public static function sampleData(): array
    {
        return [
            'cartId' => '2669842',
            'customerEmail' => 'klant@example.com',
            'cartTotal' => '€ 194,11',
            'attempts' => '9',
            'errors' => 'invoiceCountry: We herkennen dit land niet, controleer de spelling (waarde: "klant@example.com") [9x]',
            'cartUrl' => '#',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $abandonment = CheckoutAbandonment::query()->where('reason', 'validation_failed')->latest('id')->first();

        return $abandonment ? new self($abandonment) : null;
    }

    public function build()
    {
        $context = $this->mailContext();
        $subject = Translation::get(
            'admin-checkout-error-email-subject',
            'orders',
            'Klant loopt vast in de checkout (winkelwagen #:cartId:)',
            'text',
            ['cartId' => $context['cartId']]
        );

        $templateHtml = $this->renderFromTemplate($context);

        if ($templateHtml !== null) {
            [$fromEmail, $fromName] = $this->templateFrom(
                Customsetting::get('site_from_email'),
                Customsetting::get('site_name')
            );

            return $this->html($templateHtml)
                ->from($fromEmail, $fromName)
                ->subject($this->templateSubject($subject, $context));
        }

        return $this
            ->view('dashed-ecommerce-core::emails.admin-checkout-error', $context)
            ->from(Customsetting::get('site_from_email'), Customsetting::get('site_name'))
            ->subject($subject);
    }

    public function telegramSummary(): TelegramSummary
    {
        $context = $this->mailContext();

        return new TelegramSummary(
            title: 'Klant loopt vast in de checkout',
            fields: [
                'Winkelwagen' => '#' . $context['cartId'],
                'Klant' => $context['customerEmail'],
                'Bedrag' => $context['cartTotal'],
                'Pogingen' => (string) $context['attempts'],
                'Fouten' => implode("\n", $context['errorLines']),
            ],
            adminUrl: $context['cartUrl'] !== '#' ? $context['cartUrl'] : null,
            emoji: '⚠️',
            linkLabel: 'Bekijk winkelwagen in CMS',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function mailContext(): array
    {
        $lines = $this->abandonment->errorLines();

        return [
            'abandonment' => $this->abandonment,
            'cartId' => $this->abandonment->cart_id ?? '-',
            'customerEmail' => $this->abandonment->email ?: 'onbekend e-mailadres',
            'cartTotal' => '€ ' . number_format((float) $this->abandonment->cart_total, 2, ',', '.'),
            'attempts' => $this->abandonment->attempts(),
            'errorLines' => $lines,
            'errors' => implode('<br>', array_map('e', $lines)),
            'cartUrl' => $this->cartUrl(),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    private function cartUrl(): string
    {
        if (! $this->abandonment->cart_id) {
            return '#';
        }

        try {
            return CartResource::getUrl('view', ['record' => $this->abandonment->cart_id]);
        } catch (Throwable) {
            return url('/dashed/carts/' . $this->abandonment->cart_id);
        }
    }
}
