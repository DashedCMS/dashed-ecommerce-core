<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

readonly class LabelProblem
{
    public function __construct(
        public string $carrier,
        public int $labelId,
        public string $cause,
        public string $rawError,
        public ?string $field,
        public ?AddressSuggestion $suggestion,
    ) {
    }

    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'label_id' => $this->labelId,
            'cause' => $this->cause,
            'raw_error' => $this->rawError,
            'field' => $this->field,
            'suggestion' => $this->suggestion?->toArray(),
            'can_fix' => $this->canFix(),
        ];
    }

    /** Er is een adresvoorstel en de vervoerder kan een label vervangen. */
    public function canFix(): bool
    {
        return $this->suggestion !== null && LabelProblemFixer::supportsReplace($this->carrier);
    }
}
