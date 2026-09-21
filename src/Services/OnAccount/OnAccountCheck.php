<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

final class OnAccountCheck
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
    ) {
    }

    /** Tekst voor de klant, zonder bedragen. */
    public function message(): ?string
    {
        return match ($this->reason) {
            OnAccount::BLOCKED_MANUAL, OnAccount::BLOCKED_OVERDUE => __('Er staan vervallen facturen open'),
            OnAccount::OVER_LIMIT => __('Uw kredietlimiet is bereikt'),
            default => null,
        };
    }
}
