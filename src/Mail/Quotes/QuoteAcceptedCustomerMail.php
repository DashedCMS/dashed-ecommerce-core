<?php

namespace Dashed\DashedEcommerceCore\Mail\Quotes;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;

class QuoteAcceptedCustomerMail extends QuoteCustomerMail
{
    public static function emailTemplateName(): string
    {
        return 'Offerte geaccepteerd (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant nadat hij een offerte heeft geaccepteerd, met de getekende offerte als bijlage.';
    }

    public static function defaultSubject(): string
    {
        return 'Bedankt voor uw akkoord op offerte :offertenummer:';
    }

    protected function fallbackSubject(string $siteName): string
    {
        return 'Bedankt voor uw akkoord op offerte '.$this->quote->displayNumber();
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bedankt voor uw akkoord', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>We hebben uw akkoord op offerte :offertenummer: voor :onderwerp: in goede orde ontvangen. De getekende offerte zit als PDF bij deze mail.</p><p>Heeft u de bestelling nog niet geplaatst? Dat kan via de knop hieronder. Wilt u liever dat wij de bestelling voor u klaarzetten, dan hoeft u niets te doen: wij nemen contact met u op.</p>']],
            ['type' => 'button', 'data' => ['label' => 'Bestellen en betalen', 'url' => ':offerteUrl:', 'background' => ':primaryColor:', 'color' => '#ffffff']],
            ['type' => 'text', 'data' => ['body' => '<p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }

    /** Via de schijf-API: offertes staan prive (zie QuoteMail). */
    protected function withAttachments(): static
    {
        $path = ltrim(QuotePdf::path($this->quote, accepted: true), '/');

        if (! Storage::disk('dashed')->exists($path)) {
            return $this;
        }

        return $this->attachFromStorageDisk('dashed', $path, $this->quote->displayNumber().'-akkoord.pdf', ['mime' => 'application/pdf']);
    }
}
