<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class LastOrderDateCondition implements SegmentCondition
{
    public const OPERATOR_NEVER = 'never';

    private const OPERATORS = [
        'last_days' => 'in de laatste x dagen',
        'before_days' => 'langer dan x dagen geleden',
        self::OPERATOR_NEVER => 'nog nooit besteld',
    ];

    public function key(): string
    {
        return 'ecommerce.last_order_date';
    }

    public function label(): string
    {
        return __('Laatste bestelling');
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
                ->options(self::OPERATORS)
                ->default('last_days')
                ->live()
                ->required(),
            TextInput::make('value')
                ->label(__('Aantal dagen'))
                ->numeric()
                ->visible(fn (Get $get): bool => $get('operator') !== self::OPERATOR_NEVER)
                ->required(fn (Get $get): bool => $get('operator') !== self::OPERATOR_NEVER),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $operator = $config['operator'] ?? 'last_days';

        // De periode van de andere bestelconditie speelt hier geen rol: de vraag
        // is wanneer er voor het laatst besteld is, niet hoeveel er in een
        // periode besteld is. Vandaar 'all'.
        $orders = OrderConditionQuery::forSubscriber($query->getModel()->getTable(), 'all')
            ->selectRaw('max(dashed__orders.created_at)');

        // "Nog nooit besteld" is geen datumvergelijking maar de afwezigheid van
        // een bestelling. Als vergelijking geschreven zou het niets opleveren,
        // want elke vergelijking met NULL is onwaar, en dan zou het segment
        // stilzwijgend leeg blijven in plaats van precies de klanten opleveren
        // waar het om gaat.
        if ($operator === self::OPERATOR_NEVER) {
            OrderConditionQuery::compare($query, $orders, 'is null', [], $boolean);

            return;
        }

        $moment = now()->subDays((int) ($config['value'] ?? 0));
        $comparison = $operator === 'before_days' ? '<' : '>=';

        OrderConditionQuery::compare($query, $orders, $comparison . ' ?', [$moment], $boolean);
    }
}
