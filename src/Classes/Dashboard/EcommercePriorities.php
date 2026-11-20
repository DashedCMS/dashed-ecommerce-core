<?php

namespace Dashed\DashedEcommerceCore\Classes\Dashboard;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Classes\OrderListTabs;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblemDetector;

/**
 * De bronnen die ec-core in cms()->builder('dashboardPriorities') zet. Elke
 * bron is één telling in de database en levert hooguit één item: een
 * samenvatting met een link naar de gefilterde lijst, geen rij per order.
 * Een bron zonder treffers of zonder recht op de lijst levert niets.
 */
class EcommercePriorities
{
    /** @return array<string, callable> */
    public static function sources(): array
    {
        return [
            'ec-deadline' => [self::class, 'deadline'],
            'ec-priority-orders' => [self::class, 'priorityOrders'],
            'ec-label-problems' => [self::class, 'labelProblems'],
            'ec-unhandled-orders' => [self::class, 'unhandledOrders'],
            'ec-return-requests' => [self::class, 'returnRequests'],
            'ec-quotes-waiting' => [self::class, 'quotesWaiting'],
        ];
    }

    public static function deadline(): array
    {
        if (! self::can(OrderResource::class)) {
            return [];
        }

        $table = (new Order())->getTable();
        $count = Order::query()
            ->deadlineOpen()
            ->whereRaw("COALESCE({$table}.deliver_by_date, {$table}.ship_by_date) <= ?", [now()->toDateString()])
            ->count();

        return self::item($count, [
            'title' => $count === 1 ? __('1 bestelling met deadline vandaag of eerder') : __(':aantal bestellingen met deadline vandaag of eerder', ['aantal' => $count]),
            'sub' => __('Nog niet ingepakt of verzonden'),
            'url' => ResourceFilterUrl::for(OrderResource::class, ['deadline' => 'today_or_earlier']),
            'badge' => __('Vandaag'),
            'tone' => 'red',
            'sort' => 10,
        ]);
    }

    public static function priorityOrders(): array
    {
        if (! self::can(OrderResource::class)) {
            return [];
        }

        // Same rows as the link. Filters in the URL replace the whole filter
        // state, so the link carries the paid statuses itself next to the
        // open fulfillment statuses.
        $count = Order::query()
            ->isPaid()
            ->where('is_priority', true)
            ->whereIn('fulfillment_status', self::openFulfillmentStatuses())
            ->count();

        return self::item($count, [
            'title' => $count === 1 ? __('1 prioriteitsbestelling') : __(':aantal prioriteitsbestellingen', ['aantal' => $count]),
            'sub' => __('Gemarkeerd als prioriteit, nog niet ingepakt of verzonden'),
            'url' => ResourceFilterUrl::for(OrderResource::class, [
                'is_priority' => ['isActive' => true],
                'status' => ['values' => Order::PAID_STATUSES],
                'fulfillment_status' => ['values' => self::openFulfillmentStatuses()],
            ]),
            'badge' => __('Prioriteit'),
            'tone' => 'red',
            'sort' => 15,
        ]);
    }

    public static function labelProblems(): array
    {
        if (! self::can(OrderResource::class)) {
            return [];
        }

        $count = LabelProblemDetector::applyScope(Order::query())->count();

        return self::item($count, [
            'title' => __('Verzendlabels met fouten'),
            'sub' => __('De vervoerder weigerde het label'),
            'url' => ResourceFilterUrl::for(OrderResource::class, ['label_problems' => ['isActive' => true]]),
            'badge' => $count === 1 ? __('1 label') : __(':aantal labels', ['aantal' => $count]),
            'tone' => 'red',
            'sort' => 20,
        ]);
    }

    public static function unhandledOrders(): array
    {
        if (! self::can(OrderResource::class)) {
            return [];
        }

        // Same definition as the order list tab "Nieuw", which is the link.
        $count = OrderListTabs::apply('nieuw', Order::query())->count();

        return self::item($count, [
            'title' => __('Betaalde bestellingen verwerken'),
            'sub' => __('Betaald, nog niet afgehandeld'),
            'url' => OrderResource::getUrl('index', ['tab' => 'nieuw']),
            'badge' => (string) $count,
            'tone' => 'amber',
            'sort' => 30,
        ]);
    }

    public static function returnRequests(): array
    {
        if (! self::can(OrderReturnResource::class)) {
            return [];
        }

        $count = OrderReturn::query()->requested()->count();

        return self::item($count, [
            'title' => $count === 1 ? __('1 retouraanvraag beoordelen') : __(':aantal retouraanvragen beoordelen', ['aantal' => $count]),
            'sub' => __('Aangevraagd door de klant, wacht op goedkeuring'),
            'url' => ResourceFilterUrl::for(OrderReturnResource::class, ['status' => OrderReturn::STATUS_REQUESTED]),
            'badge' => (string) $count,
            'tone' => 'purple',
            'sort' => 40,
        ]);
    }

    public static function quotesWaiting(): array
    {
        if (! self::can(QuoteResource::class)) {
            return [];
        }

        $count = Quote::query()->open()->count();

        return self::item($count, [
            'title' => $count === 1 ? __('1 offerte wacht op de klant') : __(':aantal offertes wachten op de klant', ['aantal' => $count]),
            'sub' => __('Verstuurd, nog geen antwoord'),
            'url' => ResourceFilterUrl::for(QuoteResource::class, ['status' => ['values' => [Quote::STATUS_SENT]]]),
            'badge' => (string) $count,
            'tone' => 'blue',
            'sort' => 60,
        ]);
    }

    /**
     * Fulfillment statuses that still need work: everything except the
     * statuses where the deadline counts as met (packed, shipped, ...).
     *
     * @return array<int, string>
     */
    public static function openFulfillmentStatuses(): array
    {
        return array_values(array_diff(
            array_merge(array_keys(Orders::getFulfillmentStatusses()), ['partially_handled']),
            Order::DEADLINE_DONE_FULFILLMENT_STATUSES,
        ));
    }

    protected static function item(int $count, array $item): array
    {
        return $count > 0 ? [$item] : [];
    }

    protected static function can(string $resource): bool
    {
        return (bool) rescue(fn () => $resource::canViewAny(), false, false);
    }
}
