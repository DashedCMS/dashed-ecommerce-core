<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Gedeelde onderbouw van de bestelcondities voor de nieuwsbriefsegmenten.
 *
 * De twee condities stellen een andere vraag over dezelfde verzameling
 * bestellingen, dus staat het bepalen van die verzameling hier op één plek. Zou
 * elke conditie zijn eigen selectie schrijven, dan raken ze uit elkaar zodra er
 * iets bijkomt, en de eerste die dan wegvalt is de Bol-uitsluiting.
 */
class OrderConditionQuery
{
    /**
     * Periodes die een redacteur kan kiezen, met het aantal maanden terug.
     * `all` betekent geen begrenzing.
     */
    public const PERIODS = [
        'all' => 'over alle tijd',
        '1_month' => 'in de laatste maand',
        '3_months' => 'in de laatste 3 maanden',
        '6_months' => 'in de laatste 6 maanden',
        '12_months' => 'in de laatste 12 maanden',
    ];

    /**
     * De bestellingen van het contact op de huidige rij, als gecorreleerde
     * subquery. Correleren en niet vooraf uitrekenen, want een lijst met
     * tienduizenden contacten mag niet eerst als id-lijst door PHP heen.
     *
     * Een bestelling telt mee bij een contact als het e-mailadres overeenkomt,
     * of als beide aan hetzelfde account hangen. Dat tweede is nodig omdat
     * iemand kan bestellen met een ander adres dan waarmee hij zich op de lijst
     * heeft gezet. Staat er aan één van beide kanten geen account, dan levert de
     * vergelijking NULL op en telt de bestelling niet mee, wat precies goed is.
     *
     * @param string $subscriberTable de tabel van de buitenste query
     */
    public static function forSubscriber(string $subscriberTable, string $period): Builder
    {
        $orders = Order::query()
            ->isPaid()
            // Bol-bestellingen horen nooit in marketing thuis: die klant is
            // klant van Bol, niet van de winkel, en heeft hier geen toestemming
            // voor gegeven. Zelfde uitsluiting als in QueueOrderFlowEmailsListener
            // en BackfillOrderHandledFlowService.
            ->where(function (Builder $q): void {
                $q->whereNull('order_origin')->orWhere('order_origin', '!=', 'Bol');
            })
            ->where(function (Builder $q) use ($subscriberTable): void {
                $q->whereColumn('dashed__orders.email', $subscriberTable . '.email')
                    ->orWhereColumn('dashed__orders.user_id', $subscriberTable . '.user_id');
            });

        $months = static::monthsFor($period);

        if ($months !== null) {
            $orders->where('dashed__orders.created_at', '>=', now()->subMonths($months));
        }

        return $orders;
    }

    public static function monthsFor(string $period): ?int
    {
        return match ($period) {
            '1_month' => 1,
            '3_months' => 3,
            '6_months' => 6,
            '12_months' => 12,
            default => null,
        };
    }

    /**
     * Plakt een subquery als scalaire vergelijking aan de buitenste query.
     * De operator komt altijd uit een vaste lijst in de conditie zelf en nooit
     * rechtstreeks uit opgeslagen regels, zodat er niets in de SQL kan glippen.
     *
     * @param array<int, mixed> $extraBindings
     */
    public static function compare(
        Builder $query,
        Builder $subquery,
        string $expression,
        array $extraBindings,
        string $boolean,
    ): void {
        $query->whereRaw(
            '(' . $subquery->toSql() . ') ' . $expression,
            array_merge($subquery->getBindings(), $extraBindings),
            $boolean,
        );
    }
}
