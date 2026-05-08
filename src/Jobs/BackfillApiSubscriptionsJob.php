<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\ApiSubscriptionLog;

class BackfillApiSubscriptionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 3600;

    /**
     * Ruim aantal retries zodat de job zichzelf via $this->release()
     * meerdere keren kan terugleggen op de queue als een API een
     * rate-limit signaleert. Elke release telt als 1 attempt.
     */
    public int $tries = 200;

    /**
     * @param  array<int, string>  $apiClasses    Subset of fully-qualified class names uit Customsetting('apis')['class'] om mee te nemen. Leeg = alles.
     * @param  array<int, string>  $sources       Subset uit ['orders','carts','popup_views','form_inputs','users']. Leeg = alles.
     * @param  array<int, string>  $orderOrigins  Subset uit `dashed__orders.order_origin`-waardes om mee te nemen. Leeg = alle origins. Alleen relevant voor de orders-bron.
     */
    public function __construct(
        public array $apiClasses = [],
        public array $sources = [],
        public bool $onlyMarketing = false,
        public int $batchSize = 50,
        public array $orderOrigins = [],
    ) {
        $this->batchSize = max(1, min(500, $this->batchSize));
    }

    public function handle(): void
    {
        $configuredApis = collect(Customsetting::get('apis', null, []) ?? [])
            ->filter(fn ($api) => is_array($api) && ! empty($api['class']) && class_exists($api['class']))
            ->filter(fn ($api) => empty($this->apiClasses) || in_array($api['class'], $this->apiClasses, true))
            ->values()
            ->all();

        if (empty($configuredApis)) {
            Log::info('BackfillApiSubscriptionsJob: geen geldige API-configuraties gevonden, niets te doen.');

            return;
        }

        $tuples = [];

        $allSources = ['orders', 'carts', 'popup_views', 'form_inputs', 'users'];
        $sources = empty($this->sources) ? $allSources : array_values(array_intersect($allSources, $this->sources));

        foreach ($sources as $source) {
            $method = 'collectFrom' . str_replace('_', '', ucwords($source, '_'));
            if (method_exists($this, $method)) {
                try {
                    $this->{$method}($tuples);
                } catch (Throwable $e) {
                    report($e);
                    Log::warning("BackfillApiSubscriptionsJob: bron {$source} faalde: " . $e->getMessage());
                }
            }
        }

        // Dispatch 1 SyncEmailToApiJob per (email, api). Elke individuele
        // job hanteert zijn eigen rate-limit retry via release(), zodat
        // een rate-limit slechts die specifieke combi vertraagt en niet
        // de hele backfill blokkeert. De jobs worden licht gestaggered
        // via een cumulatieve delay om een thundering herd richting de
        // API te voorkomen wanneer de queue meerdere workers heeft.
        $totalDispatched = 0;
        $staggerMs = 0;

        foreach ($tuples as $email => $tuple) {
            foreach ($configuredApis as $api) {
                $apiClass = $api['class'];

                $alreadyLogged = ApiSubscriptionLog::query()
                    ->where('email', $email)
                    ->where('api_class', $apiClass)
                    ->whereIn('status', [ApiSubscriptionLog::STATUS_SUCCESS, ApiSubscriptionLog::STATUS_SKIPPED])
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                SyncEmailToApiJob::dispatch(
                    $email,
                    $tuple['first_name'] ?? null,
                    $tuple['last_name'] ?? null,
                    $apiClass,
                    $api,
                    ApiSubscriptionLog::SOURCE_BACKFILL,
                )->delay(now()->addMilliseconds($staggerMs));

                $staggerMs += 100;
                $totalDispatched++;
            }
        }

        Log::info('BackfillApiSubscriptionsJob klaar met dispatchen', [
            'unique_emails' => count($tuples),
            'apis' => count($configuredApis),
            'jobs_dispatched' => $totalDispatched,
        ]);
    }

    /**
     * Voegt een tuple toe aan de buffer. Last-seen first/last name wint zoals afgesproken.
     */
    private function add(array &$tuples, ?string $email, ?string $firstName = null, ?string $lastName = null): void
    {
        if (! $email) {
            return;
        }
        $email = mb_strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $tuples[$email] = [
            'first_name' => $firstName !== null && $firstName !== '' ? $firstName : ($tuples[$email]['first_name'] ?? null),
            'last_name' => $lastName !== null && $lastName !== '' ? $lastName : ($tuples[$email]['last_name'] ?? null),
        ];
    }

    private function collectFromOrders(array &$tuples): void
    {
        if (! Schema::hasTable('dashed__orders') || ! Schema::hasColumn('dashed__orders', 'email')) {
            return;
        }

        $query = DB::table('dashed__orders')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($this->onlyMarketing && Schema::hasColumn('dashed__orders', 'marketing')) {
            $query->where('marketing', true);
        }

        if (! empty($this->orderOrigins) && Schema::hasColumn('dashed__orders', 'order_origin')) {
            $query->whereIn('order_origin', $this->orderOrigins);
        }

        $columns = ['email'];
        if (Schema::hasColumn('dashed__orders', 'first_name')) {
            $columns[] = 'first_name';
        }
        if (Schema::hasColumn('dashed__orders', 'last_name')) {
            $columns[] = 'last_name';
        }

        $query->orderBy('id')->select($columns)->chunk(1000, function ($rows) use (&$tuples) {
            foreach ($rows as $row) {
                $this->add($tuples, $row->email ?? null, $row->first_name ?? null, $row->last_name ?? null);
            }
        });
    }

    private function collectFromCarts(array &$tuples): void
    {
        if (! Schema::hasTable('dashed__carts')) {
            return;
        }

        $emailColumn = null;
        foreach (['email', 'abandoned_email'] as $candidate) {
            if (Schema::hasColumn('dashed__carts', $candidate)) {
                $emailColumn = $candidate;

                break;
            }
        }

        if (! $emailColumn) {
            return;
        }

        $query = DB::table('dashed__carts')
            ->whereNotNull($emailColumn)
            ->where($emailColumn, '!=', '');

        $columns = [$emailColumn . ' as email'];
        if (Schema::hasColumn('dashed__carts', 'first_name')) {
            $columns[] = 'first_name';
        }
        if (Schema::hasColumn('dashed__carts', 'last_name')) {
            $columns[] = 'last_name';
        }

        $query->orderBy('id')->select($columns)->chunk(1000, function ($rows) use (&$tuples) {
            foreach ($rows as $row) {
                $this->add($tuples, $row->email ?? null, $row->first_name ?? null, $row->last_name ?? null);
            }
        });
    }

    private function collectFromPopupViews(array &$tuples): void
    {
        if (! Schema::hasTable('dashed__popup_views') || ! Schema::hasColumn('dashed__popup_views', 'email')) {
            return;
        }

        $query = DB::table('dashed__popup_views')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if (Schema::hasColumn('dashed__popup_views', 'submitted_at')) {
            $query->whereNotNull('submitted_at');
        }

        $query->orderBy('id')->select(['email'])->chunk(1000, function ($rows) use (&$tuples) {
            foreach ($rows as $row) {
                $this->add($tuples, $row->email ?? null);
            }
        });
    }

    private function collectFromFormInputs(array &$tuples): void
    {
        if (! Schema::hasTable('dashed__form_input_fields') || ! Schema::hasTable('dashed__form_fields') || ! Schema::hasTable('dashed__form_inputs')) {
            return;
        }

        $query = DB::table('dashed__form_input_fields')
            ->join('dashed__form_fields', 'dashed__form_input_fields.form_field_id', '=', 'dashed__form_fields.id')
            ->where(function ($q) {
                $q->where('dashed__form_fields.input_type', 'email')
                    ->orWhere('dashed__form_fields.name', 'like', '%email%');
            })
            ->whereNotNull('dashed__form_input_fields.value')
            ->where('dashed__form_input_fields.value', '!=', '')
            ->orderBy('dashed__form_input_fields.id')
            ->select([
                'dashed__form_input_fields.id as fif_id',
                'dashed__form_input_fields.form_input_id as form_input_id',
                'dashed__form_input_fields.value as value',
            ]);

        $query->chunk(1000, function ($rows) use (&$tuples) {
            foreach ($rows as $row) {
                $value = (string) ($row->value ?? '');
                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->add($tuples, $value);
                }
            }
        });
    }

    private function collectFromUsers(array &$tuples): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'email')) {
            return;
        }

        $query = DB::table('users')
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($this->onlyMarketing && Schema::hasColumn('users', 'marketing')) {
            $query->where('marketing', true);
        }

        $columns = ['email'];
        if (Schema::hasColumn('users', 'first_name')) {
            $columns[] = 'first_name';
        }
        if (Schema::hasColumn('users', 'last_name')) {
            $columns[] = 'last_name';
        }

        $query->orderBy('id')->select($columns)->chunk(1000, function ($rows) use (&$tuples) {
            foreach ($rows as $row) {
                $this->add($tuples, $row->email ?? null, $row->first_name ?? null, $row->last_name ?? null);
            }
        });
    }
}
