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
        return new class () {
            public function getSingleMedia(null|int|string|array $mediaId, array|string $conversion = 'medium'): object|string
            {
                // Alleen voor een test die wil zien welke conversies er worden opgevraagd:
                // zet $GLOBALS['mediahelper_stub_aanroepen'] = [] en zet hem daarna terug.
                if (isset($GLOBALS['mediahelper_stub_aanroepen'])) {
                    $GLOBALS['mediahelper_stub_aanroepen'][] = [$mediaId, $conversion];
                }

                // Alleen voor een test die een kapot media-item wil nabootsen:
                // zet $GLOBALS['mediahelper_stub_gooit_voor_id'] en zet hem daarna terug.
                if (isset($GLOBALS['mediahelper_stub_gooit_voor_id']) && $mediaId === $GLOBALS['mediahelper_stub_gooit_voor_id']) {
                    throw new RuntimeException('Kapot media-item');
                }

                // Idem voor een test die een URL per id en conversie nodig heeft:
                // zet $GLOBALS['mediahelper_stub_urls'] = ['<id>:<conversie>' => 'url'] en zet hem daarna terug.
                // Net als de echte helper komt er dan een object met `url` terug.
                if (isset($GLOBALS['mediahelper_stub_urls']) && (is_int($mediaId) || is_string($mediaId))) {
                    $sleutel = $mediaId.':'.(is_string($conversion) ? $conversion : 'medium');

                    if (isset($GLOBALS['mediahelper_stub_urls'][$sleutel])) {
                        return (object) ['url' => $GLOBALS['mediahelper_stub_urls'][$sleutel]];
                    }
                }

                return '';
            }
        };
    }
}
