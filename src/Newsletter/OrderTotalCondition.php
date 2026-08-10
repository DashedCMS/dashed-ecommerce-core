<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

class OrderTotalCondition implements SegmentCondition
{
    /**
     * Vaste lijst, want de operator gaat als tekst de SQL in. Wat hier niet in
     * staat, komt er ook niet doorheen.
     */
    private const OPERATORS = [
        '>' => 'meer dan',
        '>=' => 'meer dan of gelijk aan',
        '<' => 'minder dan',
        '<=' => 'minder dan of gelijk aan',
    ];

    public function key(): string
    {
        return 'ecommerce.order_total';
    }

    public function label(): string
    {
        return __('Besteed bedrag');
    }

    public function group(): string
    {
        return __('Bestellingen');
    }

    public function schema(): array
    {
        return [
            Select::make('period')
                ->label(__('Periode'))
                ->options(OrderConditionQuery::PERIODS)
                ->default('all')
                ->required(),
            Select::make('operator')
                ->label(__('Vergelijking'))
                ->options(self::OPERATORS)
                ->default('>')
                ->required(),
            TextInput::make('value')
                ->label(__('Bedrag'))
                ->numeric()
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $operator = isset(self::OPERATORS[$config['operator'] ?? '']) ? $config['operator'] : '>';
        $value = (float) ($config['value'] ?? 0);

        $orders = OrderConditionQuery::forSubscriber(
            $query->getModel()->getTable(),
            (string) ($config['period'] ?? 'all'),
        )->selectRaw('coalesce(sum(dashed__orders.total), 0)');

        // De cast is geen opsmuk. Een gebonden waarde gaat als tekst de query
        // in, en de linkerkant is hier een som en geen kolom, dus is er geen
        // kolomtype dat die tekst alsnog als getal laat lezen. Zonder cast
        // vergelijkt SQLite een getal met een tekst, en daar staat elk getal
        // onder elke tekst: 150 > '100' is dan onwaar. Precies de fout waar
        // value_number in FieldCondition ook voor bestaat.
        OrderConditionQuery::compare($query, $orders, $operator . ' cast(? as decimal(20,4))', [$value], $boolean);
    }
}
