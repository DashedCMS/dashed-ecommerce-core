<?php

namespace Dashed\DashedEcommerceCore\Mail\Quotes;

class QuoteExpiredCustomerMail extends QuoteCustomerMail
{
    public static function emailTemplateName(): string
    {
        return 'Offerte verlopen (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant als een offerte zijn geldigheidsdatum voorbij is.';
    }

    public static function defaultSubject(): string
    {
        return 'Offerte :offertenummer: is verlopen';
    }

    protected function fallbackSubject(string $siteName): string
    {
        return 'Offerte '.$this->quote->displayNumber().' is verlopen';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Uw offerte is verlopen', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>Offerte :offertenummer: voor :onderwerp: is verlopen. Bent u nog geïnteresseerd? Neem gerust contact met ons op, dan sturen we u een nieuwe offerte.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
