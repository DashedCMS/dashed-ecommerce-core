<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Jobs\SyncMetaAudienceJob;
use Dashed\DashedEcommerceCore\Services\Meta\Audiences\MetaAudienceSettings;

class SyncMetaAudiencesCommand extends Command
{
    protected $signature = 'dashed:meta-audiences:sync {--full : Vervang de hele doelgroep bij Meta in plaats van alleen het verschil} {--audience= : Alleen deze doelgroep (id)}';

    protected $description = 'Zet de doelgroepen gelijk met de Custom Audiences bij Meta';

    public function handle(): int
    {
        $query = MetaAudience::query()->where('is_active', true);
        if ($this->option('audience')) {
            $query->whereKey((int) $this->option('audience'));
        }

        $ready = [];
        $count = 0;
        foreach ($query->get() as $audience) {
            $ready[$audience->site_id] ??= MetaAudienceSettings::for($audience->site_id)->ready();
            if (! $ready[$audience->site_id]) {
                continue;
            }

            $audience->forceFill(['sync_status' => MetaAudience::STATUS_QUEUED])->save();
            SyncMetaAudienceJob::dispatch($audience->id, (bool) $this->option('full'));
            $count++;
        }

        $this->info("{$count} doelgroep(en) in de wachtrij gezet.");

        return self::SUCCESS;
    }
}
