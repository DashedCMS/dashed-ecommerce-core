<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Address;

use Exception;
use Illuminate\Support\Facades\Http;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Adres-lookup via postcode + huisnummer. Probeert eerst PostNL (als API key
 * beschikbaar is), valt terug op postcode.tech. Geeft een array met `city`
 * en `street` terug, of een lege array als geen van beide providers iets
 * vindt of geconfigureerd is.
 */
class AddressLookup
{
    /**
     * @return array{city?: string|null, street?: string|null}
     */
    public static function lookup(?string $zipCode, ?string $houseNr): array
    {
        $zipCode = preg_replace('/\s+/', '', (string) $zipCode) ?? '';
        $houseNr = trim((string) $houseNr);

        if ($zipCode === '' || $houseNr === '' || strlen($zipCode) < 6) {
            return [];
        }

        $postNLApiKey = Customsetting::get('checkout_postnl_api_key');
        if ($postNLApiKey) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                    'apikey' => $postNLApiKey,
                ])
                    ->retry(1, 500)
                    ->post('https://api.postnl.nl/address/national/v1/validate', [
                        'PostalCode' => $zipCode,
                        'HouseNumber' => $houseNr,
                    ])
                    ->json()[0] ?? [];

                if ($response) {
                    return [
                        'city' => $response['City'] ?? null,
                        'street' => $response['Street'] ?? null,
                    ];
                }
            } catch (Exception $e) {
                // fall through to postcode.tech
            }
        }

        $postcodeApiKey = Customsetting::get('checkout_postcode_api_key');
        if ($postcodeApiKey) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'Application/json',
                ])
                    ->withToken($postcodeApiKey)
                    ->retry(1, 500)
                    ->get('https://postcode.tech/api/v1/postcode', [
                        'postcode' => $zipCode,
                        'number' => preg_replace('/\D/', '', $houseNr),
                    ]);

                if ($response->successful()) {
                    $json = $response->json();

                    return [
                        'city' => $json['city'] ?? null,
                        'street' => $json['street'] ?? null,
                    ];
                }
            } catch (Exception $e) {
                // niks
            }
        }

        return [];
    }
}
