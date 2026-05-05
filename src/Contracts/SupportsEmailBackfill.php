<?php

namespace Dashed\DashedEcommerceCore\Contracts;

interface SupportsEmailBackfill
{
    /**
     * Synchroniseer een (email, voornaam, achternaam) tuple naar de externe API.
     *
     * Verwachte returnwaarde:
     *  ['status' => 'success'|'failed'|'skipped', 'error' => ?string]
     *
     * @param  array  $api  Configuratieblok uit Customsetting('apis'). Bevat o.a. 'list_id', 'class' en provider-specifieke velden.
     */
    public static function syncEmail(string $email, ?string $firstName, ?string $lastName, array $api): array;
}
