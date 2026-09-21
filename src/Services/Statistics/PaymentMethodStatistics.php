<?php

namespace Dashed\DashedEcommerceCore\Services\Statistics;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Query\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

/**
 * Betaalpogingen per betaalmethode.
 *
 * Een poging is een rij in order_payments, gedateerd op zijn eigen
 * created_at. Terugbetalingen en verrekeningen zijn boekhouding en tellen
 * niet mee. Het slagingspercentage is betaald / (betaald + mislukt): een open
 * poging is nog niet afgelopen en zegt dus nog niets.
 *
 * Alles loopt via gegroepeerde queries. De groepering gebeurt op methode-id
 * én tekstnaam, omdat oude rijen alleen een naam hebben; het samenvoegen van
 * die groepen gebeurt daarna in PHP, over een handvol groepen en niet over
 * losse betalingen.
 */
class PaymentMethodStatistics
{
    public const FAILED_STATUSES = ['cancelled', 'failed', 'expired'];

    /** Rijen die geen poging zijn maar boekhouding. */
    public const EXCLUDED_METHOD_NAMES = ['refund', 'verrekening'];

    /** Boven dit aantal dagen toont de grafiek weken in plaats van dagen. */
    public const WEEKS_ABOVE_DAYS = 62;

    public const CHART_METHODS = 6;

    /** @var array<string, array{id: ?int, name: string}>|null */
    private ?array $methodLookup = null;

    /**
     * @param  array<int, string>  $origins  order_origin-waarden, leeg is alles
     */
    public function __construct(
        private Carbon $start,
        private Carbon $end,
        private array $origins = [],
    ) {
    }

    /**
     * @return array<int, array{key: string, id: ?int, name: string, attempts: int, paid: int, failed: int, open: int, success_rate: ?float, amount: float}>
     */
    public function rows(): array
    {
        $groups = $this->base()
            ->select([
                'op.payment_method_id',
                'op.payment_method',
                DB::raw('COUNT(*) as attempts'),
                DB::raw("SUM(CASE WHEN op.status = 'paid' THEN 1 ELSE 0 END) as paid"),
                DB::raw('SUM(CASE WHEN ' . $this->failedCondition() . ' THEN 1 ELSE 0 END) as failed'),
                DB::raw("SUM(CASE WHEN op.status = 'paid' THEN op.amount ELSE 0 END) as amount"),
            ])
            ->groupBy('op.payment_method_id', 'op.payment_method')
            ->get();

        $rows = [];

        foreach ($groups as $group) {
            $method = $this->resolveMethod($group->payment_method_id, $group->payment_method);
            $row = $rows[$method['key']] ?? [
                'key' => $method['key'],
                'id' => $method['id'],
                'name' => $method['name'],
                'attempts' => 0,
                'paid' => 0,
                'failed' => 0,
                'amount' => 0.0,
            ];

            $row['attempts'] += (int) $group->attempts;
            $row['paid'] += (int) $group->paid;
            $row['failed'] += (int) $group->failed;
            $row['amount'] += (float) $group->amount;

            $rows[$method['key']] = $row;
        }

        return collect($rows)
            ->map(fn (array $row) => $this->withDerived($row))
            ->sortByDesc('attempts')
            ->values()
            ->all();
    }

    /**
     * @return array{attempts: int, paid: int, failed: int, open: int, success_rate: ?float, amount: float}
     */
    public function totals(?array $rows = null): array
    {
        $rows = collect($rows ?? $this->rows());

        return $this->withDerived([
            'attempts' => (int) $rows->sum('attempts'),
            'paid' => (int) $rows->sum('paid'),
            'failed' => (int) $rows->sum('failed'),
            'amount' => (float) $rows->sum('amount'),
        ]);
    }

    /**
     * Mislukte pogingen per stap (dag, of week boven WEEKS_ABOVE_DAYS dagen)
     * voor de methodes met de meeste pogingen.
     *
     * @return array{step: string, labels: array<int, string>, series: array<int, array{key: string, id: ?int, name: string, data: array<int, int>}>}
     */
    public function perStep(?array $rows = null): array
    {
        $step = $this->start->diffInDays($this->end) > self::WEEKS_ABOVE_DAYS ? 'week' : 'day';

        $starts = [];
        $cursor = $step === 'week' ? $this->start->copy()->startOfWeek() : $this->start->copy()->startOfDay();
        while ($cursor->lte($this->end)) {
            $starts[] = $cursor->copy();
            $step === 'week' ? $cursor->addWeek() : $cursor->addDay();
        }

        $indexFor = fn (Carbon $day) => $step === 'week'
            ? $day->copy()->startOfWeek()->format('Y-m-d')
            : $day->format('Y-m-d');
        $positions = array_flip(array_map(fn (Carbon $start) => $start->format('Y-m-d'), $starts));

        $chartKeys = collect($rows ?? $this->rows())
            ->take(self::CHART_METHODS)
            ->keyBy('key');

        $series = $chartKeys->map(fn (array $row) => [
            'key' => $row['key'],
            'id' => $row['id'],
            'name' => $row['name'],
            'data' => array_fill(0, count($starts), 0),
        ])->all();

        $this->base()
            ->whereRaw($this->failedCondition())
            ->select([
                DB::raw('DATE(op.created_at) as day'),
                'op.payment_method_id',
                'op.payment_method',
                DB::raw('COUNT(*) as failed'),
            ])
            ->groupBy(DB::raw('DATE(op.created_at)'), 'op.payment_method_id', 'op.payment_method')
            ->get()
            ->each(function ($group) use (&$series, $positions, $indexFor) {
                $key = $this->resolveMethod($group->payment_method_id, $group->payment_method)['key'];
                $position = $positions[$indexFor(Carbon::parse($group->day))] ?? null;

                if (isset($series[$key]) && $position !== null) {
                    $series[$key]['data'][$position] += (int) $group->failed;
                }
            });

        return [
            'step' => $step,
            'labels' => array_map(fn (Carbon $start) => $start->format('d-m-Y'), $starts),
            'series' => array_values($series),
        ];
    }

    /**
     * De pogingen in de periode, voor de actieve site en de gekozen
     * herkomsten, zonder terugbetalingen en verrekeningen.
     */
    private function base(): Builder
    {
        $payments = (new OrderPayment())->getTable();
        $orders = (new Order())->getTable();

        return DB::table("{$payments} as op")
            ->join("{$orders} as o", 'o.id', '=', 'op.order_id')
            ->whereNull('o.deleted_at')
            ->where('o.site_id', Sites::getActive())
            ->whereBetween('op.created_at', [$this->start, $this->end])
            ->where('op.amount', '>=', 0)
            ->where(fn ($query) => $query
                ->whereNull('op.payment_method')
                ->orWhereNotIn('op.payment_method', self::EXCLUDED_METHOD_NAMES))
            ->when($this->origins !== [], fn ($query) => $query->whereIn('o.order_origin', $this->origins));
    }

    private function failedCondition(): string
    {
        return "op.status IN ('" . implode("','", self::FAILED_STATUSES) . "')";
    }

    /**
     * @param  array{attempts: int, paid: int, failed: int, amount: float}  $row
     */
    private function withDerived(array $row): array
    {
        $finished = $row['paid'] + $row['failed'];

        $row['open'] = $row['attempts'] - $finished;
        $row['success_rate'] = $finished > 0 ? round($row['paid'] / $finished * 100, 1) : null;
        $row['amount'] = round($row['amount'], 2);

        return $row;
    }

    /**
     * Een groep hoort bij een methode op id; een oude rij zonder id op zijn
     * naam, en als die naam een vertaling van een bestaande methode is, bij
     * die methode.
     *
     * @return array{key: string, id: ?int, name: string}
     */
    private function resolveMethod(mixed $id, ?string $name): array
    {
        $lookup = $this->methodLookup();

        if ($id && isset($lookup['id:' . $id])) {
            return $lookup['id:' . $id];
        }

        $name = trim((string) $name);

        if ($name !== '' && isset($lookup['name:' . mb_strtolower($name)])) {
            return $lookup['name:' . mb_strtolower($name)];
        }

        return [
            'key' => 'name:' . mb_strtolower($name),
            'id' => null,
            'name' => $name !== '' ? $name : __('Onbekend'),
        ];
    }

    /**
     * @return array<string, array{key: string, id: ?int, name: string}>
     */
    private function methodLookup(): array
    {
        if ($this->methodLookup !== null) {
            return $this->methodLookup;
        }

        $lookup = [];

        foreach (PaymentMethod::withTrashed()->get() as $paymentMethod) {
            $entry = [
                'key' => 'id:' . $paymentMethod->id,
                'id' => (int) $paymentMethod->id,
                'name' => (string) ($paymentMethod->name ?: '#' . $paymentMethod->id),
            ];
            $lookup['id:' . $paymentMethod->id] = $entry;

            foreach ($paymentMethod->getTranslations('name') as $translation) {
                if (filled($translation)) {
                    $lookup['name:' . mb_strtolower(trim($translation))] ??= $entry;
                }
            }
        }

        return $this->methodLookup = $lookup;
    }
}
