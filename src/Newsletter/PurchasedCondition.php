<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

/**
 * Of het contact iets uit een categorie, of een bepaald product, besteld
 * heeft (of juist niet), in de gekozen periode. Een bestelregel telt op het
 * product_id; een product dat later uit de categorie is gehaald telt dus
 * niet meer mee, want de categorie wordt nu gelezen en niet bij de bestelling
 * vastgelegd.
 */
class PurchasedCondition implements SegmentCondition
{
    public function key(): string
    {
        return 'ecommerce.purchased';
    }

    public function label(): string
    {
        return __('Kocht uit categorie of product');
    }

    public function group(): string
    {
        return __('Bestellingen');
    }

    public function schema(): array
    {
        return [
            Select::make('operator')
                ->label(__('Vergelijking'))
                ->options([
                    'has' => __('heeft gekocht'),
                    'has_not' => __('heeft niet gekocht'),
                ])
                ->default('has')
                ->required(),
            Select::make('type')
                ->label(__('Uit'))
                ->options([
                    'category' => __('Categorie'),
                    'product' => __('Product'),
                ])
                ->default('category')
                ->live()
                ->required(),
            Select::make('category_ids')
                ->label(__('Categorieën'))
                ->multiple()
                ->searchable()
                ->options(fn () => ProductCategory::query()->thisSite()->get()->mapWithKeys(fn ($c) => [$c->id => (string) $c->name])->all())
                ->visible(fn (Get $get): bool => $get('type') !== 'product')
                ->required(fn (Get $get): bool => $get('type') !== 'product'),
            Select::make('product_ids')
                ->label(__('Producten'))
                ->multiple()
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Product::query()->search($search)->limit(50)->get()->mapWithKeys(fn ($p) => [$p->id => (string) $p->name])->all())
                ->getOptionLabelsUsing(fn (array $values) => Product::query()->whereIn('id', $values)->get()->mapWithKeys(fn ($p) => [$p->id => (string) $p->name])->all())
                ->visible(fn (Get $get): bool => $get('type') === 'product')
                ->required(fn (Get $get): bool => $get('type') === 'product'),
            Select::make('period')
                ->label(__('Periode'))
                ->options(OrderConditionQuery::PERIODS)
                ->default('all')
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $type = ($config['type'] ?? 'category') === 'product' ? 'product' : 'category';
        $ids = array_values(array_filter(array_map('intval', (array) ($config[$type . '_ids'] ?? []))));
        $not = ($config['operator'] ?? 'has') === 'has_not';

        $orders = OrderConditionQuery::forSubscriber($query->getModel()->getTable(), (string) ($config['period'] ?? 'all'))
            ->join('dashed__order_products', 'dashed__order_products.order_id', '=', 'dashed__orders.id')
            ->selectRaw('count(*)');

        if ($type === 'product') {
            $orders->whereIn('dashed__order_products.product_id', $ids === [] ? [0] : $ids);
        } else {
            $orders->whereIn('dashed__order_products.product_id', function ($sub) use ($ids): void {
                $sub->select('product_id')->from('dashed__product_category')
                    ->whereIn('product_category_id', $ids === [] ? [0] : $ids);
            });
        }

        OrderConditionQuery::compare($query, $orders, $not ? '= 0' : '> 0', [], $boolean);
    }
}
