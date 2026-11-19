<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Meta\Audiences;

use RuntimeException;

/**
 * Een fout van of rond de Marketing API. `retryable` zegt of het zin heeft
 * het later opnieuw te proberen (limiet, storing bij Meta) of dat er eerst
 * iemand iets moet doen (verkeerd token, geen rechten op het account).
 */
class MetaAudienceException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
