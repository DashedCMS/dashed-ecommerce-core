<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Dagtellers van de browser-events naar Meta's Conversions API. Die events
 * krijgen geen logregel per stuk (dat zijn er duizenden per dag); hier staat
 * per site, dag en eventsoort hoeveel er verstuurd en mislukt zijn.
 */
class MetaCapiEventCount extends Model
{
    public const SENT = 'sent';
    public const FAILED = 'failed';

    protected $table = 'dashed__meta_capi_event_counts';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'sent' => 'integer',
        'failed' => 'integer',
    ];

    /** Telt één event bij de dag van vandaag, in één atomaire query. */
    public static function record(string $siteId, string $eventName, string $column, int $amount = 1): void
    {
        if (! in_array($column, [self::SENT, self::FAILED], true) || $amount < 1) {
            return;
        }

        $now = Carbon::now();

        DB::table((new static())->getTable())->upsert(
            [[
                'site_id' => $siteId,
                'date' => $now->toDateString(),
                'event_name' => mb_substr($eventName, 0, 60),
                self::SENT => $column === self::SENT ? $amount : 0,
                self::FAILED => $column === self::FAILED ? $amount : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['site_id', 'date', 'event_name'],
            [$column => DB::raw("{$column} + {$amount}"), 'updated_at' => $now],
        );
    }

    /**
     * De laatste dagen, nieuwste eerst, met per eventsoort verstuurd en mislukt.
     *
     * @return array<int, array{date: string, events: array<string, array{sent: int, failed: int}>}>
     */
    public static function overview(int $days = 7, ?string $siteId = null): array
    {
        $days = max(1, $days);
        $from = Carbon::today()->subDays($days - 1);

        $rows = static::query()
            ->where('date', '>=', $from->toDateString())
            ->when($siteId, fn ($query) => $query->where('site_id', $siteId))
            ->get()
            ->groupBy(fn (self $row) => $row->date->toDateString());

        $empty = array_fill_keys(array_keys(MetaCapiSettings::BROWSER_EVENTS), [self::SENT => 0, self::FAILED => 0]);
        $overview = [];

        for ($day = Carbon::today(); $day->gte($from); $day = $day->copy()->subDay()) {
            $events = $empty;

            foreach ($rows->get($day->toDateString(), []) as $row) {
                $events[$row->event_name] ??= [self::SENT => 0, self::FAILED => 0];
                $events[$row->event_name][self::SENT] += $row->sent;
                $events[$row->event_name][self::FAILED] += $row->failed;
            }

            $overview[] = ['date' => $day->toDateString(), 'events' => $events];
        }

        return $overview;
    }
}
