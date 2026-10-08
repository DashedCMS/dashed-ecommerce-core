<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Wat de bedankpagina aan de browserpixel meegeeft om met het server-side
 * event te ontdubbelen: hetzelfde event-ID en dezelfde waarde. Staat de
 * Conversions API uit, dan blijft de waarde de oude en ontbreekt metaContents,
 * zodat de pixel exact zijn eigen contents blijft opbouwen. Een fout levert een
 * lege array op: een optionele tracking-uitbreiding mag de bedankpagina niet breken.
 */
class MetaBrowserPayload
{
    /** @return array{metaEventId?: string, metaValue?: string, metaContents?: array<int, array<string, mixed>>} */
    public static function forOrder(Order $order): array
    {
        try {
            $settings = MetaCapiSettings::for($order->site_id);
            $enabled = $settings->enabled();
            $valueMode = $enabled ? $settings->valueMode() : MetaCapiSettings::VALUE_INCL_VAT;

            $payload = [
                'metaEventId' => ConversionsApi::eventId($order),
                'metaValue' => number_format(MetaPurchaseValue::for($order, $valueMode), 2, '.', ''),
            ];

            if ($enabled) {
                $payload['metaContents'] = MetaPurchaseValue::contents($order);
            }

            return $payload;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
