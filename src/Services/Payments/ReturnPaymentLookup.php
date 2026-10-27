<?php

namespace Dashed\DashedEcommerceCore\Services\Payments;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

/**
 * Zoekt de betaling bij een terugkeer van de betaalprovider op de
 * bedankpagina. Die pagina toont naam, adres en bestelregels, dus de
 * sleutel moet onraadbaar zijn: de hash van de betaling (32 tekens), die
 * Mollie, Pay.nl en MultiSafePay alle drie als `paymentId` in de return-URL
 * meekrijgen.
 *
 * Het transactie-id van de provider (`psp_id`) is geen geheim: het staat in
 * mails, dashboards en logs van de provider, en bij sommige providers is het
 * oplopend. Daarom telt het alleen als terugval voor een betaling van de
 * laatste PSP_ID_WINDOW_MINUTES, de tijd waarin een klant van het
 * betaalscherm terugkomt. De exchange (server naar server) zoekt wel altijd op
 * psp_id; die toont niets.
 */
class ReturnPaymentLookup
{
    public const PARAMETERS = [
        'orderId',
        'order_id',
        'paymentId',
        'id',
        'transactionid',
    ];

    public const PSP_ID_WINDOW_MINUTES = 120;

    public static function fromRequest(Request $request): ?OrderPayment
    {
        $values = collect(self::PARAMETERS)
            ->map(fn (string $parameter) => $request->get($parameter))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->values();

        foreach ($values as $value) {
            if ($orderPayment = OrderPayment::where('hash', $value)->first()) {
                return $orderPayment;
            }
        }

        foreach ($values as $value) {
            $orderPayment = OrderPayment::where('psp_id', $value)
                ->where('created_at', '>=', now()->subMinutes(self::PSP_ID_WINDOW_MINUTES))
                ->latest('id')
                ->first();

            if ($orderPayment) {
                return $orderPayment;
            }
        }

        return null;
    }
}
