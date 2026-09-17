<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

/**
 * Zegt welke verplichte GS1-velden na de drie lagen van Gs1MetaResolver nog
 * leeg of ongeldig zijn, gegroepeerd per eerste categorie van een product,
 * en bewaart wat de beheerder invult op die categorie (of als
 * winkelstandaard), zodat een volgende run het niet opnieuw vraagt.
 */
class Gs1MissingFields
{
    /** Veld => kolom op product en categorie; de winkelstandaard is `gs1_default_` plus het deel na `gs1_`. */
    public const COLUMNS = [
        'classification' => 'gs1_classification',
        'consumerUnit' => 'gs1_consumer_unit',
        'packagingType' => 'gs1_packaging_type',
        'country' => 'gs1_country',
        'language' => 'gs1_language',
        'brand' => 'gs1_brand',
        'quantity' => 'gs1_quantity',
        'unit' => 'gs1_unit',
    ];

    private const FREE_TEXT = ['brand', 'quantity'];

    public function __construct(
        private readonly Gs1ReferenceData $reference,
        private readonly ?string $siteId,
    ) {
    }

    /**
     * @return list<string>
     */
    public function problems(Gs1Row $row): array
    {
        $problems = [];

        foreach (array_keys(self::COLUMNS) as $field) {
            $value = $field === 'quantity'
                ? ($row->quantity ? (string) $row->quantity : null)
                : $row->{$field};

            if ($value === null || $value === '') {
                $problems[] = $field;
            } elseif (! in_array($field, self::FREE_TEXT, true) && ! $this->reference->isValid($field, $value)) {
                $problems[] = $field;
            } elseif ($field === 'brand' && mb_strlen($value) > 70) {
                $problems[] = $field;
            }
        }

        if (! $row->description) {
            $problems[] = 'description';
        }

        if ($row->subBrand && mb_strlen($row->subBrand) > 70) {
            $problems[] = 'subBrand';
        }

        return $problems;
    }

    /**
     * @param  iterable<Product>  $products  met productCategories geladen
     * @return array<string, array{key: string, category_id: ?int, label: string, fields: list<string>, product_ids: list<int>}>
     */
    public function groups(iterable $products): array
    {
        $resolver = new Gs1MetaResolver($this->siteId);
        $groups = [];

        foreach ($products as $product) {
            $problems = $this->problems($resolver->resolve($product));
            if ($problems === []) {
                continue;
            }

            $category = $product->productCategories->first();
            $key = $category ? 'cat-' . $category->id : 'shop';

            $groups[$key] ??= [
                'key' => $key,
                'category_id' => $category?->id,
                'label' => $category ? (string) $category->name : __('Producten zonder categorie'),
                'fields' => [],
                'product_ids' => [],
            ];
            $groups[$key]['fields'] = array_values(array_unique([...$groups[$key]['fields'], ...$problems]));
            $groups[$key]['product_ids'][] = $product->id;
        }

        return $groups;
    }

    /**
     * @param  array<string, array<string, mixed>>  $answers
     */
    public function store(array $answers): void
    {
        // Eerst alles valideren, pas daarna schrijven: anders staat een
        // eerdere groep al opgeslagen terwijl een latere groep alsnog een
        // InvalidArgumentException gooit.
        $validated = [];

        foreach ($answers as $key => $fields) {
            $fields = array_intersect_key(
                array_filter((array) $fields, fn ($value) => $value !== null && $value !== ''),
                self::COLUMNS,
            );

            foreach ($fields as $field => $value) {
                if (! in_array($field, self::FREE_TEXT, true) && ! $this->reference->isValid($field, (string) $value)) {
                    throw new \InvalidArgumentException(__('Ongeldige waarde voor :veld: :waarde', ['veld' => self::label($field), 'waarde' => $value]));
                }
            }

            $validated[$key] = $fields;
        }

        DB::transaction(function () use ($validated) {
            foreach ($validated as $key => $fields) {
                if ($key === 'shop') {
                    foreach ($fields as $field => $value) {
                        Customsetting::set('gs1_default_' . Str::after(self::COLUMNS[$field], 'gs1_'), self::cast($field, $value, forSetting: true), $this->siteId);
                    }

                    continue;
                }

                $category = ProductCategory::find((int) Str::after($key, 'cat-'));
                if (! $category) {
                    continue;
                }

                foreach ($fields as $field => $value) {
                    $category->{self::COLUMNS[$field]} = self::cast($field, $value, forSetting: false);
                }
                $category->saveQuietly();
            }
        });
    }

    public static function answerable(string $field): bool
    {
        return isset(self::COLUMNS[$field]);
    }

    public static function label(string $field): string
    {
        return match ($field) {
            'classification' => __('Productclassificatie'),
            'consumerUnit' => __('Gaat naar de consument'),
            'packagingType' => __('Verpakkingstype'),
            'country' => __('Land'),
            'language' => __('Taal'),
            'brand' => __('Merk'),
            'quantity' => __('Aantal'),
            'unit' => __('Eenheid'),
            'description' => __('Productomschrijving'),
            'subBrand' => __('Submerk'),
            default => $field,
        };
    }

    private static function cast(string $field, mixed $value, bool $forSetting): mixed
    {
        return match ($field) {
            'consumerUnit' => $forSetting ? (int) ($value === 'Ja') : $value === 'Ja',
            'quantity' => (int) $value,
            default => (string) $value,
        };
    }
}
