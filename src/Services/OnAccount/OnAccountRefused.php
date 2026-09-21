<?php

namespace Dashed\DashedEcommerceCore\Services\OnAccount;

use RuntimeException;

class OnAccountRefused extends RuntimeException
{
    public function __construct(public readonly OnAccountCheck $check, ?string $message = null)
    {
        parent::__construct($message ?? $check->message() ?? __('Op rekening bestellen is nu niet mogelijk'));
    }
}
