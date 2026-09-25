<?php

namespace Dashed\DashedEcommerceCore\Mail\Quotes;

class QuoteRejectedCustomerMail extends QuoteCustomerMail
{
    public static function emailTemplateName(): string
    {
        return 'Offerte afgewezen (klant)';
    }

    public static function emailTemplateDescription(): ?string
    {
        return 'Bevestiging aan de klant dat zijn afwijzing van een offerte binnen is.';
    }

    public static function defaultSubject(): string
    {
        return 'Uw reactie op offerte :offertenummer:';
    }

    protected function fallbackSubject(string $siteName): string
    {
        return 'Uw reactie op offerte '.$this->quote->displayNumber();
    }

    public static function defaultBlocks(): array
    {
        return [
            ['type' => 'heading', 'data' => ['text' => 'Bedankt voor uw reactie', 'level' => 'h1']],
            ['type' => 'text', 'data' => ['body' => '<p>Beste :klantnaam:,</p><p>We hebben ontvangen dat u offerte :offertenummer: voor :onderwerp: niet accepteert. Jammer, maar bedankt dat u het ons laat weten.</p><p>Wilt u een aangepaste offerte, of heeft u vragen? Beantwoord gerust deze mail.</p><p>Met vriendelijke groet,<br>Het team van :siteName:</p>']],
        ];
    }
}
