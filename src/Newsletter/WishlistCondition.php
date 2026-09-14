<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\WishlistItem;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

/**
 * Segmentconditie: heeft dit contact (niet) producten op zijn verlanglijst
 * staan, eventueel beperkt tot een categorie. Wordt alleen geladen wanneer
 * dashed-newsletter aanwezig is (zie de `booted()`-registratie in de
 * serviceprovider), want dat package levert de `SegmentCondition`-interface.
 */
class WishlistCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'ecommerce.wishlist';
    }

    public function label(): string
    {
        return __('Producten op verlanglijst');
    }

    public function group(): string
    {
        return __('Verlanglijst');
    }

    public function schema(): array
    {
        return [
            Select::make('operator')
                ->label(__('Heeft'))
                ->options(['has' => __('producten op de verlanglijst'), 'has_not' => __('geen producten op de verlanglijst')])
                ->default('has')
                ->required(),
            Select::make('category_id')
                ->label(__('Uit categorie (optioneel)'))
                ->options(fn () => ProductCategory::query()->pluck('name', 'id'))
                ->searchable(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $subscriberTable = $query->getModel()->getTable();

        $items = WishlistItem::query()
            ->join('dashed__wishlists', 'dashed__wishlists.id', '=', 'dashed__wishlist_items.wishlist_id')
            ->join('dashed__products', 'dashed__products.id', '=', 'dashed__wishlist_items.product_id')
            ->where('dashed__products.public', 1)
            ->where(function (Builder $q) use ($subscriberTable): void {
                $q->whereColumn('dashed__wishlists.email', $subscriberTable . '.email')
                    ->orWhereColumn('dashed__wishlists.user_id', $subscriberTable . '.user_id');
            });

        if ($categoryId = (int) ($config['category_id'] ?? 0)) {
            $ids = $this->categoryWithChildren($categoryId);

            // Twee EXISTS in plaats van één join met OR: de categorie kan aan
            // het product zelf hangen, of aan de productgroep erachter. Zie
            // App\Newsletter\CategoryPurchasedCondition voor dezelfde regel bij
            // aankopen.
            $items->where(function (Builder $q) use ($ids): void {
                $q->whereExists(function ($sub) use ($ids): void {
                    $sub->selectRaw('1')
                        ->from('dashed__product_category')
                        ->whereColumn('dashed__product_category.product_id', 'dashed__products.id')
                        ->whereIn('dashed__product_category.product_category_id', $ids);
                })->orWhereExists(function ($sub) use ($ids): void {
                    $sub->selectRaw('1')
                        ->from('dashed__product_category')
                        ->whereColumn('dashed__product_category.product_group_id', 'dashed__products.product_group_id')
                        ->whereIn('dashed__product_category.product_category_id', $ids);
                });
            });
        }

        $items->selectRaw('count(*)');

        OrderConditionQuery::compare($query, $items, (($config['operator'] ?? 'has') === 'has' ? '> 0' : '= 0'), [], $boolean);
    }

    /** @return array<int, int> */
    private function categoryWithChildren(int $categoryId): array
    {
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $children = ProductCategory::query()->whereIn('parent_id', $queue)->pluck('id')->all();
            $queue = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $queue);
        }

        return $ids;
    }
}
