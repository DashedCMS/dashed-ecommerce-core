<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

readonly class ParsedCarrierError
{
    public function __construct(
        public string $description,
        public ?string $field,
        public string $raw,
    ) {
    }
}
