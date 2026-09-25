<?php

namespace Dashed\DashedEcommerceCore\Mail\Quotes;

class QuoteWithdrawnCustomerMail extends QuoteCustomerMail
{
    public static function emailTemplateName(): string
    {
        return 'Offerte ingetrokken (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Verzonden naar de klant als een offerte wordt ingetrokken.';
    }

    public static function defaultSubject(): string
    {
        return 'Offerte :offertenummer: is ingetrokken';
    }

    protected function fallbackSubject(string $siteName): string
    {
        return 'Offerte '.$this->quote->displayNumber().' is ingetrokken';
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Offerte ingetrokken', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>Offerte :offertenummer: voor :onderwerp: is ingetrokken en kan niet meer geaccepteerd worden.</p><p>Heeft u vragen, of wilt u een nieuwe offerte? Beantwoord gerust deze mail.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
