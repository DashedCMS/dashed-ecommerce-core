<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

/** Voorstel om verzendkosten in te houden op een creditorder. */
class FreeShippingReversalProposal
{
    public function __construct(
        public readonly float $amount,
        public readonly float $threshold,
        public readonly float $valueBefore,
        public readonly float $valueAfter,
        public readonly string $methodName,
    ) {
    }
}
