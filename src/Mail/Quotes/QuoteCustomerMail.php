<?php

namespace Dashed\DashedEcommerceCore\Mail\Quotes;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Mail\EmailRenderer;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteBranding;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteDefaults;

/**
 * Gedeelde opbouw van de klantmails rond een offerte-antwoord. Elke subklasse
 * is een eigen template in het mailtemplatescherm; alleen de teksten
 * verschillen.
 */
abstract class QuoteCustomerMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Quote $quote)
    {
    }

    public static function availableVariables(): array
    {
        return ['offertenummer', 'klantnaam', 'onderwerp', 'offerteUrl', 'siteName', 'primaryColor'];
    }

    abstract protected function fallbackSubject(string $siteName): string;

    public static function sampleData(): array
    {
        $quote = Quote::query()->latest()->first();

        return [
            'quote' => $quote,
            'offertenummer' => $quote?->displayNumber() ?? 'OFF-2026-1001',
            'klantnaam' => $quote?->fullName() ?: 'Jan Jansen',
            'onderwerp' => $quote?->title ?? 'Maatwerk',
            'offerteUrl' => $quote?->publicUrl() ?? url('/quote/demo'),
            'siteName' => Customsetting::get('site_name'),
        ];
    }

    public static function makeForTest(): ?static
    {
        $quote = Quote::query()->latest()->first();

        return $quote ? new static($quote) : null;
    }

    public function build()
    {
        $brand = QuoteBranding::for($this->quote);
        $siteName = $brand->companyName();

        $context = [
            'quote' => $this->quote,
            'offertenummer' => $this->quote->displayNumber(),
            'klantnaam' => $this->quote->fullName() ?: (string) $this->quote->company_name,
            'onderwerp' => (string) $this->quote->title,
            'offerteUrl' => $this->quote->publicUrl(),
            'siteName' => $siteName,
            'primaryColor' => $brand->color(),
        ];

        $fallbackSubject = $this->fallbackSubject($siteName);
        $templateHtml = $this->renderFromTemplate($context);

        [$fromEmail, $fromName] = $this->templateFrom(
            QuoteDefaults::fromEmail() ?? Customsetting::get('site_from_email'),
            $siteName,
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

        return $this->withAttachments();
    }

    protected function withAttachments(): static
    {
        return $this;
    }
}
