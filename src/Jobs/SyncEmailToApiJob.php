<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\ApiSubscriptionLog;
use Dashed\DashedEcommerceCore\Contracts\SupportsEmailBackfill;

/**
 * Synchroniseert 1 (email, voornaam, achternaam) tuple naar 1 geconfigureerde
 * API-class. Wordt gedispatched door BackfillApiSubscriptionsJob (1 job per
 * email per api) zodat een rate-limit slechts deze ene job vertraagt en niet
 * de hele backfill blokkeert. De alreadyLogged-check zorgt dat herhaalde
 * dispatches voor dezelfde combi geen dubbele API-calls produceren.
 */
class SyncEmailToApiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    /**
     * Ruim aantal retries zodat de job zichzelf via release() meerdere keren
     * kan terugleggen op de queue als de API een rate-limit signaleert.
     */
    public int $tries = 100;

    /**
     * @param  class-string  $apiClass
     */
    public function __construct(
        public string $email,
        public ?string $firstName,
        public ?string $lastName,
        public string $apiClass,
        public array $api,
        public string $source = ApiSubscriptionLog::SOURCE_BACKFILL,
    ) {
    }

    public function handle(): void
    {
        $apiClass = $this->apiClass;

        if (! class_exists($apiClass)) {
            return;
        }

        if (! is_subclass_of($apiClass, SupportsEmailBackfill::class) && ! method_exists($apiClass, 'syncEmail')) {
            ApiSubscriptionLog::record(
                $this->email,
                $apiClass,
                $this->source,
                ApiSubscriptionLog::STATUS_SKIPPED,
                'API class ondersteunt geen syncEmail',
            );

            return;
        }

        // Skip dubbele dispatches: als deze (email, api) al eerder met
        // success of skipped is gelogd, hoeven we geen nieuwe API-call
        // te doen.
        $alreadyLogged = ApiSubscriptionLog::query()
            ->where('email', $this->email)
            ->where('api_class', $apiClass)
            ->whereIn('status', [ApiSubscriptionLog::STATUS_SUCCESS, ApiSubscriptionLog::STATUS_SKIPPED])
            ->exists();

        if ($alreadyLogged) {
            return;
        }

        try {
            $result = $apiClass::syncEmail(
                $this->email,
                $this->firstName,
                $this->lastName,
                $this->api,
            );
        } catch (Throwable $e) {
            report($e);
            ApiSubscriptionLog::record(
                $this->email,
                $apiClass,
                $this->source,
                ApiSubscriptionLog::STATUS_FAILED,
                mb_substr($e->getMessage(), 0, 1000),
            );

            return;
        }

        // Rate-limit signaal: zonder log te schrijven released de job
        // zichzelf naar de queue met de retry-after delay. Bij volgende
        // run blijft alreadyLogged false en wordt syncEmail opnieuw
        // aangeroepen.
        if (($result['status'] ?? null) === 'rate_limited') {
            $delay = max(30, (int) ($result['retry_after'] ?? 60) + 5);
            Log::info('SyncEmailToApiJob: rate-limit, release naar queue', [
                'api_class' => $apiClass,
                'email' => $this->email,
                'retry_after_seconds' => $delay,
                'message' => $result['error'] ?? null,
            ]);
            $this->release($delay);

            return;
        }

        $status = $result['status'] ?? ApiSubscriptionLog::STATUS_FAILED;
        $error = $result['error'] ?? null;

        ApiSubscriptionLog::record($this->email, $apiClass, $this->source, $status, $error);
    }
}
