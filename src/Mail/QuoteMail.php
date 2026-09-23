<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Mail\EmailRenderer;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteTotals;

class QuoteMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Quote $quote, public ?string $customMessage = null)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Offerte (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant met de offerte als bijlage en een link naar de offertepagina.';
    }

    public static function availableVariables(): array
    {
        return ['offertenummer', 'klantnaam', 'onderwerp', 'totaal', 'geldig_tot', 'offerteUrl', 'bericht', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Offerte :offertenummer: van :siteName:';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Uw offerte staat klaar', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>Hierbij onze offerte voor :onderwerp:. De offerte is geldig tot en met :geldig_tot: en het totaalbedrag is :totaal:.</p>']],
            ['type' => 'text', 'data' => ['body' => '<p>:bericht:</p>']],
            ['type' => 'button', 'data' => ['label' => 'Offerte bekijken', 'url' => ':offerteUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'divider', 'data' => []],
            ['type' => 'text', 'data' => ['body' => '<p>Op de offertepagina kunt u eventuele keuzes aanvinken en de offerte accepteren of afwijzen. De offerte zit ook als PDF bij deze mail.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    public static function sampleData(): array
    {
        $quote = Quote::query()->latest()->first();

        return [
            'quote' => $quote,
            'offertenummer' => $quote?->displayNumber() ?? 'OFF-2026-1001',
            'klantnaam' => $quote?->fullName() ?: 'Jan Jansen',
            'onderwerp' => $quote?->title ?? 'Maatwerk',
            'totaal' => CurrencyHelper::formatPrice($quote ? (float) $quote->total : 968.00),
            'geldig_tot' => $quote?->valid_until?->format('d-m-Y') ?? now()->addDays(14)->format('d-m-Y'),
            'offerteUrl' => $quote?->publicUrl() ?? url('/quote/demo'),
            'bericht' => '',
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?self
    {
        $quote = Quote::query()->latest()->first();

        return $quote ? new self($quote) : null;
    }

    public function build()
    {
        $siteName = Customsetting::get('site_name', Sites::getActive(), '');
        $primaryColor = Customsetting::get('mail_primary_color', Sites::getActive(), '') ?: '#A0131C';

        $context = [
            'quote' => $this->quote,
            'offertenummer' => $this->quote->displayNumber(),
            'klantnaam' => $this->quote->fullName() ?: $this->quote->company_name,
            'onderwerp' => (string) $this->quote->title,
            'totaal' => CurrencyHelper::formatPrice(QuoteTotals::for($this->quote)->total),
            'geldig_tot' => $this->quote->valid_until?->format('d-m-Y') ?? '',
            'offerteUrl' => $this->quote->publicUrl(),
            'bericht' => (string) $this->customMessage,
            'siteName' => $siteName,
            'primaryColor' => $primaryColor,
        ];

        $fallbackSubject = 'Offerte '.$this->quote->displayNumber().' van '.$siteName;
        $templateHtml = $this->renderFromTemplate($context);

        [$fromEmail, $fromName] = $this->templateFrom(
            \Dashed\DashedEcommerceCore\Services\Quotes\QuoteDefaults::fromEmail() ?? Customsetting::get('site_from_email'),
            Customsetting::get('site_name'),
        );

        if ($templateHtml === null) {
            $defaultTemplate = new EmailTemplate();
            $defaultTemplate->mailable_key = static::emailTemplateKey();
            $defaultTemplate->setTranslations('subject', [$this->quote->locale => $fallbackSubject]);
            $defaultTemplate->setTranslations('blocks', [$this->quote->locale => static::defaultBlocks()]);
            $templateHtml = app(EmailRenderer::class)->render($defaultTemplate, $context);
            $subject = $fallbackSubject;
        } else {
            $subject = $this->templateSubject($fallbackSubject, $context);
        }

        $this->html($templateHtml)->from($fromEmail, $fromName)->subject($subject);

        return $this->attachQuotePdf();
    }

    /**
     * Via de schijf-API en niet via attach(Storage::url(...)): offertes staan
     * prive, en een pad-bijlage breekt daar met een nietszeggende "array offset
     * on null" in symfony's TextPart.
     */
    private function attachQuotePdf(): static
    {
        $path = ltrim(QuotePdf::path($this->quote), '/');

        if (! Storage::disk('dashed')->exists($path)) {
            return $this;
        }

        return $this->attachFromStorageDisk('dashed', $path, $this->quote->displayNumber().'.pdf', [
            'mime' => 'application/pdf',
        ]);
    }
}
