<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Lost een labelprobleem in één klik op: neemt het adresvoorstel over op de
 * order, logt dat en vervangt het mislukte label door een nieuw label met
 * dezelfde instellingen.
 */
class LabelProblemFixer
{
    /** Velden van het verzendadres die een voorstel mag wijzigen. */
    private const SHIPPING_FIELDS = ['street', 'house_nr', 'zip_code', 'city', 'country'];

    private const CARRIERS = [
        'veloyd' => [
            'model' => 'Dashed\DashedEcommerceVeloyd\Models\VeloydOrder',
            'class' => 'Dashed\DashedEcommerceVeloyd\Classes\Veloyd',
        ],
        'myparcel' => [
            'model' => 'Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder',
            'class' => 'Dashed\DashedEcommerceMyParcel\Classes\MyParcel',
        ],
    ];

    /**
     * @return array{ok: bool, message: string}
     */
    public static function apply(Order $order, LabelProblem $problem, ?int $userId = null): array
    {
        if (! $problem->suggestion) {
            return ['ok' => false, 'message' => __('Er is geen adresvoorstel voor dit label. Pas het adres handmatig aan.')];
        }

        $changes = array_intersect_key($problem->suggestion->changes, array_flip(self::SHIPPING_FIELDS));
        if ($changes === []) {
            return ['ok' => false, 'message' => __('Het adresvoorstel bevat geen wijzigingen.')];
        }

        $config = self::CARRIERS[$problem->carrier] ?? null;
        if (! $config || ! class_exists($config['model']) || ! class_exists($config['class'])) {
            return ['ok' => false, 'message' => __('Vervoerder :carrier is niet beschikbaar.', ['carrier' => $problem->carrier])];
        }

        $label = ($config['model'])::where('order_id', $order->id)->whereKey($problem->labelId)->first();
        if (! $label) {
            return ['ok' => false, 'message' => __('Het label is niet meer gevonden. Vernieuw de pagina.')];
        }

        $descriptions = [];
        foreach ($changes as $field => $value) {
            $descriptions[] = self::fieldLabel($field) . ' ' . trim((string) $order->{$field}) . ' -> ' . $value;
            $order->{$field} = $value;
        }
        $order->save();

        OrderLog::createLog(
            orderId: (int) $order->id,
            tag: 'order.details.changed',
            note: __('Verzendadres aangepast na labelfout: :changes (bron: :source)', [
                'changes' => implode(', ', $descriptions),
                'source' => $problem->suggestion->source,
            ]),
            userId: $userId,
        );

        try {
            ($config['class'])::replaceLabel($label);
        } catch (Throwable $e) {
            report($e);

            $cause = LabelProblemDetector::cause(CarrierErrorParser::parse($e->getMessage()), $order->fresh());

            return ['ok' => false, 'message' => __('Adres aangepast, maar het nieuwe label mislukte: :cause', ['cause' => $cause])];
        }

        return ['ok' => true, 'message' => __('Adres aangepast en nieuw label aangemaakt.')];
    }

    private static function fieldLabel(string $field): string
    {
        return match ($field) {
            'zip_code' => 'postcode',
            'house_nr' => 'huisnummer',
            'street' => 'straat',
            'city' => 'plaats',
            default => 'land',
        };
    }
}
