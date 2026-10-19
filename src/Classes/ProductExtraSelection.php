<?php

namespace Dashed\DashedEcommerceCore\Classes;

/**
 * Houdt de gekozen product-extra's vast als de bezoeker van variatie wisselt.
 *
 * De extra's van de nieuwe variant komen als verse array binnen; wat de
 * bezoeker op de vorige variant al had gekozen wordt daarin teruggezet zolang
 * dat op de nieuwe variant nog kan. Een extra wordt herkend op id (gedeelde,
 * globale extra's) of anders op naam en type (eigen extra's per variant);
 * een optie op id of anders op haar tekst.
 */
class ProductExtraSelection
{
    protected const SESSION_KEY = 'dashed.cart.carried-extras';

    public static function carryOver(array $previous, array $next): array
    {
        if (! $previous) {
            return $next;
        }

        foreach ($next as $index => $extra) {
            $old = static::findPrevious($previous, $extra);

            if (! $old) {
                continue;
            }

            $value = static::carriedValue($old, $extra);

            if ($value !== null) {
                $next[$index]['value'] = $value;
            }
        }

        return $next;
    }

    /**
     * Zet de keuze klaar voor de volgende paginalading, voor sites die na een
     * variatiewissel doorsturen naar de pagina van de nieuwe variant.
     */
    public static function remember(int|string $productGroupId, array $extras): void
    {
        $values = collect($extras)
            ->filter(fn ($extra) => static::filled($extra['value'] ?? null))
            ->values()
            ->all();

        if ($values) {
            session()->put(static::SESSION_KEY . '.' . $productGroupId, $values);
        }
    }

    public static function pull(int|string $productGroupId): array
    {
        return (array) session()->pull(static::SESSION_KEY . '.' . $productGroupId, []);
    }

    protected static function findPrevious(array $previous, array $extra): ?array
    {
        foreach ($previous as $old) {
            if (($old['id'] ?? null) && ($old['id'] ?? null) == ($extra['id'] ?? null)) {
                return $old;
            }
        }

        $name = static::normalize($extra['name'] ?? null);

        if ($name === '') {
            return null;
        }

        foreach ($previous as $old) {
            if (($old['type'] ?? null) === ($extra['type'] ?? null) && static::normalize($old['name'] ?? null) === $name) {
                return $old;
            }
        }

        return null;
    }

    protected static function carriedValue(array $old, array $extra): mixed
    {
        $value = $old['value'] ?? null;

        if (! static::filled($value)) {
            return null;
        }

        $options = $extra['product_extra_options'] ?? [];
        $oldOptions = $old['product_extra_options'] ?? [];

        return match ($extra['type'] ?? null) {
            'single', 'imagePicker' => $value === true
                ? true
                : static::findOption($options, $value, $oldOptions)['id'] ?? null,
            'checkbox' => $options[0]['id'] ?? true,
            'multiple' => static::carriedMultiple((array) $value, $options, $oldOptions) ?: null,
            default => $value,
        };
    }

    protected static function carriedMultiple(array $values, array $options, array $oldOptions): array
    {
        $carried = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $option = static::findOption($options, $value, $oldOptions);

                if ($option) {
                    $carried[] = is_string($value) ? (string) $option['id'] : $option['id'];
                }

                continue;
            }

            if (static::filled($value)) {
                $carried[] = $value;
            }
        }

        return array_values(array_unique($carried, SORT_REGULAR));
    }

    /**
     * Zoekt een optie op id, en anders op de tekst van de optie die dat id in
     * de vorige lijst had (eigen extra's per variant hebben andere optie-id's).
     */
    protected static function findOption(array $options, mixed $id, array $oldOptions): ?array
    {
        foreach ($options as $option) {
            if (($option['id'] ?? null) == $id) {
                return $option;
            }
        }

        $text = '';

        foreach ($oldOptions as $oldOption) {
            if (($oldOption['id'] ?? null) == $id) {
                $text = static::normalize($oldOption['value'] ?? null);

                break;
            }
        }

        if ($text === '') {
            return null;
        }

        foreach ($options as $option) {
            if (static::normalize($option['value'] ?? null) === $text) {
                return $option;
            }
        }

        return null;
    }

    protected static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== false && $value !== [];
    }

    /**
     * Naam en optietekst komen uit toArray() als vertaalarray; vergelijk in de
     * actieve taal, en anders op de eerste vertaling die er is.
     */
    protected static function normalize(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[app()->getLocale()] ?? (reset($value) ?: null);
        }

        return is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
    }
}
