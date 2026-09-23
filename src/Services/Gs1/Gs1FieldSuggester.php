<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;

/**
 * Stelt voor ontbrekende GS1-velden een waarde voor uit de download zelf:
 * wat producten in dezelfde categorie die al bij GS1 staan het vaakst
 * gebruiken, en anders wat het hele bestand het vaakst gebruikt. Een
 * GTIN over meerdere regels telt één keer; alleen geldige waarden tellen.
 */
class Gs1FieldSuggester
{
    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_FILE = 'file';

    public function __construct(private readonly Gs1ReferenceData $reference)
    {
    }

    /**
     * @param  array<int, Gs1Row>  $rows  uit Gs1FileReader
     * @param  array<string, array{key: string, category_id: ?int, fields: list<string>}>  $groups  uit Gs1MissingFields::groups()
     * @return array<string, array<string, array{value: string, count: int, total: int, scope: string}>>
     */
    public function suggest(array $rows, array $groups): array
    {
        $byGtin = [];
        foreach ($rows as $row) {
            if ($row->hasRealGtin()) {
                $byGtin[$row->gtin] ??= $row;
            }
        }

        $suggestions = [];

        foreach ($groups as $key => $group) {
            $peers = $group['category_id'] ? $this->categoryRows($byGtin, (int) $group['category_id']) : [];

            foreach ($group['fields'] as $field) {
                if (! Gs1MissingFields::answerable($field)) {
                    continue;
                }

                $suggestion = $this->mostCommon($peers, $field, self::SCOPE_CATEGORY)
                    ?? $this->mostCommon($byGtin, $field, self::SCOPE_FILE);

                if ($suggestion) {
                    $suggestions[$key][$field] = $suggestion;
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, Gs1Row>  $byGtin
     * @return array<string, Gs1Row>
     */
    private function categoryRows(array $byGtin, int $categoryId): array
    {
        $eans = Product::query()
            ->whereNotNull('ean')
            ->whereHas('productCategories', fn ($query) => $query->whereKey($categoryId))
            ->pluck('ean')
            ->map(fn ($ean) => (string) $ean)
            ->all();

        return array_intersect_key($byGtin, array_flip($eans));
    }

    /**
     * @param  array<string, Gs1Row>  $rows
     * @return array{value: string, count: int, total: int, scope: string}|null
     */
    private function mostCommon(array $rows, string $field, string $scope): ?array
    {
        $counts = [];
        foreach ($rows as $row) {
            $value = $field === 'quantity'
                ? ($row->quantity ? (string) $row->quantity : null)
                : $row->{$field};

            if ($this->usable($field, $value)) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return null;
        }

        // arsort houdt bij gelijke aantallen de eerst geziene waarde vooraan.
        arsort($counts);

        return [
            'value' => (string) array_key_first($counts),
            'count' => reset($counts),
            'total' => count($rows),
            'scope' => $scope,
        ];
    }

    private function usable(string $field, ?string $value): bool
    {
        return match ($field) {
            'brand' => $value !== null && $value !== '' && mb_strlen($value) <= 70,
            'quantity' => $value !== null && (int) $value > 0,
            default => $this->reference->isValid($field, $value),
        };
    }
}
