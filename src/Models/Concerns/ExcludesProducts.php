<?php

namespace Dashed\DashedEcommerceCore\Models\Concerns;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;

/**
 * Uitsluitingen voor een globale extra, tab of FAQ. Wie een categorie koppelt
 * maar een paar productgroepen of producten daaruit niet wil hebben, zet die
 * hier. Uitsluiten wint altijd, ook van een directe koppeling.
 */
trait ExcludesProducts
{
    public function initializeExcludesProducts(): void
    {
        $this->mergeCasts([
            'excluded_product_ids' => 'array',
            'excluded_product_group_ids' => 'array',
        ]);
    }

    public function excludesProduct(Product $product): bool
    {
        if (in_array($product->id, $this->excludedIds('excluded_product_ids'), true)) {
            return true;
        }

        return $product->product_group_id
            && $this->excludesProductGroup((int) $product->product_group_id);
    }

    public function excludesProductGroup(int $productGroupId): bool
    {
        return in_array($productGroupId, $this->excludedIds('excluded_product_group_ids'), true);
    }

    public static function withoutExcludedFor(Collection $items, Product | ProductGroup $model): Collection
    {
        return $items->reject(fn ($item) => $model instanceof Product
            ? $item->excludesProduct($model)
            : $item->excludesProductGroup($model->id))->values();
    }

    public static function exclusionFormSection(): Section
    {
        return Section::make(__('Uitsluiten'))
            ->description(__('Productgroepen en producten die dit nooit krijgen, ook niet via een gekoppelde categorie.'))
            ->columnSpanFull()
            ->collapsible()
            ->schema([
                static::exclusionSelect('excluded_product_group_ids', __('Productgroepen uitsluiten'), ProductGroup::class),
                static::exclusionSelect('excluded_product_ids', __('Producten uitsluiten'), Product::class),
            ]);
    }

    protected static function exclusionSelect(string $column, string $label, string $model): Select
    {
        return Select::make($column)
            ->label($label)
            ->multiple()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make($model, $search))
            ->getOptionLabelsUsing(fn (array $values) => $model::query()
                ->whereIn('id', $values)
                ->get()
                ->mapWithKeys(fn ($record) => [$record->id => $record->nameWithParents])
                ->all());
    }

    protected function excludedIds(string $column): array
    {
        return array_map('intval', (array) ($this->{$column} ?? []));
    }
}
