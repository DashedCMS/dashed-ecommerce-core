<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Meta\Audiences;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\MetaAudience;
use Dashed\DashedEcommerceCore\Models\MetaAudienceLog;
use Dashed\DashedEcommerceCore\Models\MetaAudienceMember;

/**
 * Zet een doelgroep gelijk met Meta.
 *
 * Incrementeel: vergelijkt de gewenste leden met wat er volgens
 * `dashed__meta_audience_members` bij Meta staat, haalt eerst weg wat eruit
 * moet en voegt dan toe. Elke geslaagde batch wordt meteen in die tabel
 * vastgelegd, zodat een herkansing na een fout alleen het restant stuurt.
 *
 * Volledig: een vervangsessie (`usersreplace`) met alle gewenste leden. Dat
 * herstelt een doelgroep die bij Meta uit de pas liep, bijvoorbeeld omdat
 * iemand er in Ads Manager zelf aan zat.
 */
class MetaAudienceSyncer
{
    public function __construct(
        private readonly MetaAudienceSource $source = new MetaAudienceSource(),
        private readonly ?MetaAudienceApi $api = null,
    ) {
    }

    public function batchSize(): int
    {
        return max(1, min(10000, (int) config('dashed-ecommerce-core.meta_audiences.batch_size', 10000)));
    }

    public function sync(MetaAudience $audience, bool $full = false): MetaAudienceLog
    {
        $settings = MetaAudienceSettings::for($audience->site_id);
        if (! $settings->ready()) {
            throw new MetaAudienceException(__('Doelgroepen naar Meta staan uit of het advertentieaccount of token ontbreekt.'));
        }

        $api = $this->api ?? MetaAudienceApi::for($settings);

        if (! $audience->meta_audience_id) {
            $id = $api->createAudience(
                (string) $settings->adAccountId(),
                $audience->name,
                (string) __('Gesynchroniseerd vanuit het CMS: :bron', ['bron' => $audience->sourceLabel()]),
            );
            $audience->forceFill(['meta_audience_id' => $id])->save();
            MetaAudienceLog::create([
                'meta_audience_id' => $audience->id,
                'type' => MetaAudienceLog::TYPE_CREATE,
                'status' => MetaAudienceLog::STATUS_SUCCESS,
                'message' => 'id '.$id,
            ]);
            $full = false; // Een nieuwe doelgroep is leeg: incrementeel is dan precies alles toevoegen.
        }

        $desired = $this->source->members($audience);

        [$added, $removed] = $full
            ? $this->replace($api, $audience, $desired)
            : $this->incremental($api, $audience, $desired);

        $audience->forceFill([
            'member_count' => count($desired),
            'sync_status' => MetaAudience::STATUS_SYNCED,
            'last_error' => null,
            'last_synced_at' => now(),
            'last_full_sync_at' => $full ? now() : $audience->last_full_sync_at,
        ])->save();

        return MetaAudienceLog::create([
            'meta_audience_id' => $audience->id,
            'type' => $full ? MetaAudienceLog::TYPE_FULL : MetaAudienceLog::TYPE_INCREMENTAL,
            'status' => MetaAudienceLog::STATUS_SUCCESS,
            'total' => count($desired),
            'added' => $added,
            'removed' => $removed,
        ]);
    }

    /**
     * @param array<string, string> $desired
     * @return array{0: int, 1: int}
     */
    private function incremental(MetaAudienceApi $api, MetaAudience $audience, array $desired): array
    {
        /** @var array<string, string> $existing */
        $existing = MetaAudienceMember::query()
            ->where('meta_audience_id', $audience->id)
            ->pluck('phone_hash', 'email_hash')
            ->map(fn ($phone) => (string) $phone)
            ->all();

        $toRemove = [];
        foreach ($existing as $email => $phone) {
            // Een ander telefoonnummer: het oude paar eruit, het nieuwe erin.
            if (! array_key_exists($email, $desired) || $desired[$email] !== $phone) {
                $toRemove[$email] = $phone;
            }
        }

        $toAdd = [];
        foreach ($desired as $email => $phone) {
            if (! array_key_exists($email, $existing) || $existing[$email] !== $phone) {
                $toAdd[$email] = $phone;
            }
        }

        foreach (array_chunk($toRemove, $this->batchSize(), true) as $batch) {
            $api->removeUsers((string) $audience->meta_audience_id, $this->rows($batch));
            MetaAudienceMember::query()
                ->where('meta_audience_id', $audience->id)
                ->whereIn('email_hash', array_keys($batch))
                ->delete();
        }

        foreach (array_chunk($toAdd, $this->batchSize(), true) as $batch) {
            $api->addUsers((string) $audience->meta_audience_id, $this->rows($batch));
            $this->store($audience, $batch);
        }

        // Een gewijzigd telefoonnummer staat in beide lijsten maar is één persoon.
        $changed = count(array_intersect_key($toRemove, $toAdd));

        return [count($toAdd) - $changed, count($toRemove) - $changed];
    }

    /**
     * @param array<string, string> $desired
     * @return array{0: int, 1: int}
     */
    private function replace(MetaAudienceApi $api, MetaAudience $audience, array $desired): array
    {
        $existing = MetaAudienceMember::query()->where('meta_audience_id', $audience->id)->pluck('email_hash')->flip();

        if ($desired === []) {
            // Een vervangsessie zonder gegevens weigert Meta; leeg maken kan
            // alleen door de bekende leden weg te halen.
            [, $removed] = $this->incremental($api, $audience, []);

            return [0, $removed];
        }

        $sessionId = MetaAudienceApi::newSessionId();
        $batches = array_chunk($desired, $this->batchSize(), true);
        $last = count($batches);

        foreach ($batches as $index => $batch) {
            $api->replaceUsers(
                (string) $audience->meta_audience_id,
                $this->rows($batch),
                $sessionId,
                $index + 1,
                $index + 1 === $last,
                count($desired),
            );
        }

        DB::transaction(function () use ($audience, $desired) {
            MetaAudienceMember::query()->where('meta_audience_id', $audience->id)->delete();
            foreach (array_chunk($desired, 1000, true) as $chunk) {
                $this->store($audience, $chunk);
            }
        });

        $added = 0;
        foreach ($desired as $email => $phone) {
            if (! isset($existing[$email])) {
                $added++;
            }
        }

        return [$added, count(array_diff_key($existing->all(), $desired))];
    }

    /**
     * @param array<string, string> $members
     * @return list<array{0: string, 1: string}>
     */
    private function rows(array $members): array
    {
        $rows = [];
        foreach ($members as $email => $phone) {
            $rows[] = [(string) $email, (string) $phone];
        }

        return $rows;
    }

    /** @param array<string, string> $members */
    private function store(MetaAudience $audience, array $members): void
    {
        $now = now();
        $rows = [];
        foreach ($members as $email => $phone) {
            $rows[] = [
                'meta_audience_id' => $audience->id,
                'email_hash' => (string) $email,
                'phone_hash' => $phone === '' ? null : $phone,
                'created_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MetaAudienceMember::query()->upsert($chunk, ['meta_audience_id', 'email_hash'], ['phone_hash']);
        }
    }
}
