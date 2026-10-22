<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedNewsletter\Segments\Contracts\SegmentCondition;

/**
 * Hoe vaak het contact besteld heeft: het aantal betaalde bestellingen in de
 * gekozen periode, vergeleken met een getal. Zelfde verzameling bestellingen
 * als de andere bestelcondities (OrderConditionQuery), dus ook hier geen
 * Bol-bestellingen.
 */
class OrderCountCondition implements SegmentCondition
{
    private const OPERATORS = [
        '>=' => 'minstens',
        '<=' => 'hoogstens',
        '=' => 'precies',
    ];

    public function key(): string
    {
        return 'ecommerce.order_count';
    }

    public function label(): string
    {
        return __('Aantal bestellingen');
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
                ->default('>=')
                ->required(),
            TextInput::make('value')
                ->label(__('Aantal'))
                ->numeric()
                ->minValue(0)
                ->required(),
        ];
    }

    public function apply(Builder $query, array $config, string $boolean): void
    {
        $operator = isset(self::OPERATORS[$config['operator'] ?? '']) ? $config['operator'] : '>=';

        $orders = OrderConditionQuery::forSubscriber(
            $query->getModel()->getTable(),
            (string) ($config['period'] ?? 'all'),
        )->selectRaw('count(*)');

        // Gecast om dezelfde reden als in OrderTotalCondition: een gebonden
        // waarde is tekst, en SQLite zet elk getal onder elke tekst.
        OrderConditionQuery::compare($query, $orders, $operator . ' cast(? as integer)', [max(0, (int) ($config['value'] ?? 0))], $boolean);
    }
}
