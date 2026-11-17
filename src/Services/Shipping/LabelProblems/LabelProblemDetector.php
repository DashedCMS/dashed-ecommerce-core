<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;

class LabelProblemDetector
{
    private const CARRIERS = [
        'veloyd' => ['relation' => 'veloydOrders', 'model' => 'Dashed\DashedEcommerceVeloyd\Models\VeloydOrder'],
        'myparcel' => ['relation' => 'myParcelOrders', 'model' => 'Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder'],
    ];

    /**
     * @param  bool  $withSuggestion  false = alleen de oorzaak, zonder PDOK-zoektocht (bv. printdaemon)
     */
    public static function forOrder(Order $order, bool $withSuggestion = true): ?LabelProblem
    {
        $best = null;

        foreach (self::CARRIERS as $carrier => $config) {
            if (! class_exists($config['model']) || ! Order::hasDynamicRelation($config['relation'])) {
                continue;
            }

            $label = $order->{$config['relation']}()
                ->where('is_return', false)
                ->latest('id')
                ->first();

            if (! $label || trim((string) $label->error) === '' || self::hasPdf($label)) {
                continue;
            }

            if (! $best || $label->updated_at > $best[1]->updated_at) {
                $best = [$carrier, $label];
            }
        }

        if (! $best) {
            return null;
        }

        [$carrier, $label] = $best;
        $raw = (string) $label->error;
        $parsed = CarrierErrorParser::parse($raw);

        return new LabelProblem(
            $carrier,
            (int) $label->id,
            self::cause($parsed, $order),
            $raw,
            $parsed->field,
            $withSuggestion ? self::suggestion($parsed->field, $order) : null,
        );
    }

    /** @return array<string, array{relation: string, model: string}> */
    public static function carriers(): array
    {
        return self::CARRIERS;
    }

    /** Beperkt een labelquery tot niet-retourlabels met een opgeslagen fout. */
    public static function constrainToErrors($query)
    {
        return $query->whereNotNull('error')->where('error', '!=', '')->where('is_return', false);
    }

    public static function applyScope(Builder $orders): Builder
    {
        return $orders->where(function (Builder $q) {
            $any = false;
            foreach (self::CARRIERS as $config) {
                if (! class_exists($config['model']) || ! Order::hasDynamicRelation($config['relation'])) {
                    continue;
                }
                $q->orWhereHas($config['relation'], fn ($l) => self::constrainToErrors($l));
                $any = true;
            }
            if (! $any) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    private static function hasPdf($label): bool
    {
        $path = (string) ($label->label_pdf_path ?? '');

        return $path !== '' && Storage::disk('public')->exists($path);
    }

    public static function cause(ParsedCarrierError $parsed, Order $order): string
    {
        $value = match ($parsed->field) {
            'zip_code' => $order->zip_code,
            'house_nr' => $order->house_nr,
            'street' => $order->street,
            'city' => $order->city,
            default => null,
        };
        $value = trim((string) $value);

        return match ($parsed->field) {
            'zip_code' => __('Postcode :value is ongeldig of onvolledig.', ['value' => $value]),
            'house_nr' => __('Huisnummer :value is ongeldig.', ['value' => $value]),
            'street' => __('Straat :value is ongeldig.', ['value' => $value]),
            'city' => __('Plaats :value is ongeldig.', ['value' => $value]),
            default => __('De vervoerder weigerde het label: :description', ['description' => $parsed->description]),
        };
    }

    private static function suggestion(?string $field, Order $order): ?AddressSuggestion
    {
        $eligible = in_array($field, ['zip_code', 'street', 'city'], true)
            || ($field === null && ! preg_match('/^\d{4}\s?[A-Z]{2}$/i', trim((string) $order->zip_code)));

        if (! $eligible) {
            return null;
        }

        return AddressSuggester::suggest(
            (string) $order->country,
            $order->street,
            $order->house_nr,
            $order->zip_code,
            $order->city,
        );
    }
}
