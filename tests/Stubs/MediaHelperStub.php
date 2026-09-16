<?php

declare(strict_types=1);

/**
 * ProductsBlock::renderProducts() (hergebruikt door WishlistBlock) roept de
 * globale mediaHelper() aan, geleverd door het dashed-files-package. Dat
 * package is geen dependency van dashed-ecommerce-core (het is een
 * peer-package dat een consumerende app zelf installeert), dus de
 * Testbench-skeleton van dit package kent de functie niet. Minimale stub met
 * hetzelfde contract als Dashed\DashedFiles\Classes\MediaHelper::getSingleMedia():
 * geen media-id → lege string, precies wat een product zonder afbeelding
 * (zoals in deze testsuite) oplevert.
 *
 * Alleen gedefinieerd als de functie nog niet bestaat (bijv. wanneer dit ooit
 * wel binnen een echte app-context met dashed-files draait).
 */
if (! function_exists('mediaHelper')) {
    function mediaHelper(): object
    {
        return new class
        {
            public function getSingleMedia(null|int|string|array $mediaId, array|string $conversion = 'medium'): string
            {
                return '';
            }
        };
    }
}
