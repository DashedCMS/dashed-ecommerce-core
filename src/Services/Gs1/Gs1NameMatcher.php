<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Database\Eloquent\Builder;

/**
 * Koppelt een GS1-omschrijving aan een product op naam, in elke locale.
 * Gedeeld door de losse EAN-sync en de run-analyse, zodat er één
 * definitie is van "dezelfde naam".
 */
final class Gs1NameMatcher
{
    /**
     * @return array<string, int> genormaliseerde naam => product-id; bij dubbele namen wint het laagste id
     */
    public function index(Builder $query): array
    {
        $index = [];

        (clone $query)
            ->select(['id', 'name'])
            ->chunkById(500, function ($products) use (&$index) {
                foreach ($products as $product) {
                    $translations = method_exists($product, 'getTranslations')
                        ? $product->getTranslations('name')
                        : ['_' => $product->name ?? null];

                    foreach ($translations as $name) {
                        if (! $name) {
                            continue;
                        }
                        $key = self::normalize((string) $name);
                        $index[$key] ??= $product->id;
                    }
                }
            });

        return $index;
    }

    public static function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)));
    }
}
