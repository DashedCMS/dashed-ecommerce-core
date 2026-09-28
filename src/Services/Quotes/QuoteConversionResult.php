<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccount;

/**
 * Uitkomst van QuoteConverter::convert(): de order (als die er is), waar de
 * klant heen moet om te betalen, en de reden van een weigering.
 */
final class QuoteConversionResult
{
    public function __construct(
        public readonly ?Order $order = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $refusal = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->order !== null && $this->refusal === null;
    }

    /**
     * Leesbare Nederlandse tekst bij de weigeringscode, voor een beheerder in
     * het CMS. De code zelf (bijvoorbeeld "blocked_overdue") zegt een
     * beheerder niets.
     */
    public function refusalMessage(): ?string
    {
        return match ($this->refusal) {
            null => null,
            'not_accepted' => __('De offerte is (nog) niet geaccepteerd'),
            'no_customer' => __('Op rekening kan alleen met een klantaccount op de offerte'),
            'no_email' => __('De offerte heeft geen e-mailadres voor de betaallink'),
            'not_placed' => __('Er is al een bestelling aangemaakt, maar die is nog niet geplaatst; rond hem af vanuit de bestelling'),
            OnAccount::NOT_ENABLED => __('Deze klant heeft geen betaalmethode op rekening gekoppeld'),
            OnAccount::NOT_LOGGED_IN => __('Op rekening kan alleen met een klantaccount op de offerte'),
            OnAccount::BLOCKED_MANUAL => __('Deze klant is geblokkeerd voor bestellen op rekening'),
            OnAccount::BLOCKED_OVERDUE => __('Er staan vervallen facturen open bij deze klant'),
            OnAccount::OVER_LIMIT => __('De kredietlimiet van deze klant is bereikt'),
            default => __('Onbekende reden: :reden', ['reden' => $this->refusal]),
        };
    }
}
