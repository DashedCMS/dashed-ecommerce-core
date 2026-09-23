<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Mail\EmailRenderer;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Models\EmailTemplate;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedCore\Mail\Concerns\HasEmailTemplate;
use Dashed\DashedCore\Mail\Contracts\RegistersEmailTemplate;

class QuoteReminderMail extends Mailable implements RegistersEmailTemplate
{
    use HasEmailTemplate;
    use Queueable;
    use SerializesModels;

    public function __construct(public Quote $quote)
    {
    }

    public static function emailTemplateName(): string
    {
        return 'Offerte verloopt binnenkort (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Herinnering aan de klant een aantal dagen voordat de offerte verloopt.';
    }

    public static function availableVariables(): array
    {
        return ['offertenummer', 'klantnaam', 'onderwerp', 'geldig_tot', 'offerteUrl', 'siteName', 'primaryColor'];
    }

    public static function defaultSubject(): string
    {
        return 'Uw offerte :offertenummer: verloopt binnenkort';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Uw offerte verloopt binnenkort', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>Onze offerte voor :onderwerp: is geldig tot en met :geldig_tot:. Wilt u er nog gebruik van maken, laat het ons dan weten.</p>']],
            ['type' => 'button', 'data' => ['label' => 'Offerte bekijken', 'url' => ':offerteUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
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
            'geldig_tot' => $quote?->valid_until?->format('d-m-Y') ?? now()->addDays(3)->format('d-m-Y'),
            'offerteUrl' => $quote?->publicUrl() ?? url('/quote/demo'),
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
            'geldig_tot' => $this->quote->valid_until?->format('d-m-Y') ?? '',
            'offerteUrl' => $this->quote->publicUrl(),
            'siteName' => $siteName,
            'primaryColor' => $primaryColor,
        ];

        $fallbackSubject = 'Uw offerte '.$this->quote->displayNumber().' verloopt binnenkort';
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

            return $this->html(app(EmailRenderer::class)->render($defaultTemplate, $context))
                ->from($fromEmail, $fromName)
                ->subject($fallbackSubject);
        }

        return $this->html($templateHtml)
            ->from($fromEmail, $fromName)
            ->subject($this->templateSubject($fallbackSubject, $context));
    }
}
