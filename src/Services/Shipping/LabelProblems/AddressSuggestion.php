<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

readonly class AddressSuggestion
{
    /**
     * @param  array<string, string>  $changes  veld => nieuwe waarde (alleen zip_code/street/city)
     */
    public function __construct(
        public array $changes,
        public string $label,
        public string $source = 'PDOK',
    ) {
    }

    public function toArray(): array
    {
        return [
            'changes' => $this->changes,
            'label' => $this->label,
            'source' => $this->source,
        ];
    }
}
