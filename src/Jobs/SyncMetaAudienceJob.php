<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Models\MetaAudienceLog;
use Dashed\DashedEcommerceCore\Services\Meta\Audiences\MetaAudienceSyncer;
use Dashed\DashedEcommerceCore\Services\Meta\Audiences\MetaAudienceException;

/**
 * Synchroniseert één doelgroep. Een limiet of storing bij Meta wordt met
 * oplopende tussenpoos opnieuw geprobeerd; een fout waar iemand iets aan moet
 * doen (token, rechten, verwijderd segment) niet: die staat in het logboek en
 * op de doelgroep, en de volgende geplande ronde probeert het weer.
 */
class SyncMetaAudienceJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $timeout = 900;

    public int $uniqueFor = 3600;

    public function __construct(public int $audienceId, public bool $full = false)
    {
    }

    public function uniqueId(): string
    {
        return 'meta-audience-'.$this->audienceId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(MetaAudienceSyncer $syncer): void
    {
        $audience = MetaAudience::find($this->audienceId);
        if (! $audience || ! $audience->is_active) {
            return;
        }

        try {
            $syncer->sync($audience, $this->full);
        } catch (MetaAudienceException $e) {
            $willRetry = $e->retryable && $this->attempts() < $this->tries;

            MetaAudienceLog::create([
                'meta_audience_id' => $audience->id,
                'type' => $this->full ? MetaAudienceLog::TYPE_FULL : MetaAudienceLog::TYPE_INCREMENTAL,
                'status' => $willRetry ? MetaAudienceLog::STATUS_RETRYING : MetaAudienceLog::STATUS_FAILED,
                'message' => $e->getMessage(),
            ]);

            $audience->forceFill([
                'sync_status' => MetaAudience::STATUS_FAILED,
                'last_error' => $e->getMessage(),
            ])->save();

            if ($willRetry) {
                throw $e;
            }
        }
    }
}
