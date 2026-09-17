<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Search\SearchIndexer;
use Dashed\DashedEcommerceCore\Models\Gs1Run;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Gs1RunLine;
use Illuminate\Contracts\Cache\LockTimeoutException;

/**
 * Voert een run uit. `plan()` rekent uit wat er gebeurt en wordt ook voor
 * het voorbeeld gebruikt, zodat voorbeeld en uitvoering dezelfde uitkomst
 * hebben. `apply()` controleert vlak voor elke schrijfactie opnieuw, want
 * tussen analyseren en toewijzen kan iemand anders een product gewijzigd
 * hebben.
 */
class Gs1Assigner
{
    /**
     * Eén slot voor alle runs: twee toewijzingen tegelijk lezen dezelfde
     * producten zonder EAN en zouden ze allebei willen vullen.
     */
    private const LOCK = 'gs1-apply';

    private const LOCK_SECONDS = 120;

    private const LOCK_WAIT_SECONDS = 3;

    public function __construct(private readonly Gs1FileUpdater $updater)
    {
    }

    public function plan(Gs1Run $run): Gs1Plan
    {
        $lines = $run->lines()->get();

        $fixed = [];
        foreach ($lines as $line) {
            $isFixed = ($line->kind === Gs1RunLine::KIND_NAAM_MATCH && $line->decision === Gs1RunLine::DECISION_TOEWIJZEN)
                || ($line->kind === Gs1RunLine::KIND_WEES && $line->decision === Gs1RunLine::DECISION_KOPPELEN);

            if ($isFixed && $line->product_id) {
                $fixed[] = ['line_id' => $line->id, 'product_id' => $line->product_id, 'gtin' => $line->gtin, 'reused' => false];
            }
        }

        $missing = new Gs1MissingFields(Gs1ReferenceData::fromArray($run->reference_data ?? []), $run->site_id);
        $resolver = new Gs1MetaResolver($run->site_id);
        $blocked = [];
        $candidates = [];

        $query = Product::query()
            ->needsGs1Code()
            ->whereNotIn('id', array_column($fixed, 'product_id'))
            ->with(['productCategories', 'productGroup']);

        foreach ($query->lazyById(200) as $product) {
            $problems = $missing->problems($resolver->resolve($product));
            if ($problems !== []) {
                $blocked[$product->id] = $problems;
            } else {
                $candidates[] = $product->id;
            }
        }

        $pool = $lines
            ->filter(fn (Gs1RunLine $line) => $line->kind === Gs1RunLine::KIND_POOL
                || ($line->kind === Gs1RunLine::KIND_WEES && $line->decision === Gs1RunLine::DECISION_VRIJGEVEN))
            ->sort(fn (Gs1RunLine $a, Gs1RunLine $b) => [$a->isReuse(), $a->gtin] <=> [$b->isReuse(), $b->gtin])
            ->values();

        $assignments = $fixed;
        foreach ($candidates as $index => $productId) {
            if (! isset($pool[$index])) {
                break;
            }
            $assignments[] = [
                'line_id' => $pool[$index]->id,
                'product_id' => $productId,
                'gtin' => $pool[$index]->gtin,
                'reused' => $pool[$index]->isReuse(),
            ];
        }

        return new Gs1Plan(
            $assignments,
            array_values(array_slice($candidates, $pool->count())),
            $blocked,
        );
    }

    public function apply(Gs1Run $run): Gs1Plan
    {
        if (! $run->isConcept()) {
            throw new Gs1RunLockedException(__('Deze run is al toegewezen of afgesloten.'));
        }

        $assignedIds = [];

        $plan = $this->withLock(function () use ($run, &$assignedIds) {
            return DB::transaction(function () use ($run, &$assignedIds) {
                // Opnieuw lezen onder het slot: het object kan verouderd zijn.
                $locked = Gs1Run::query()->whereKey($run->getKey())->lockForUpdate()->first();
                if (! $locked || ! $locked->isConcept()) {
                    throw new Gs1RunLockedException(__('Deze run is al toegewezen of afgesloten.'));
                }
                $run->setRawAttributes($locked->getAttributes(), true);

                $plan = $this->plan($run);
                $assigned = 0;
                $skipped = 0;

                foreach ($plan->assignments as $assignment) {
                    $line = Gs1RunLine::findOrFail($assignment['line_id']);
                    $product = Product::find($assignment['product_id']);
                    $reason = $this->blockReason($product, $assignment['gtin']);

                    if ($reason !== null) {
                        $line->update(['skip_reason' => $reason]);
                        $skipped++;

                        continue;
                    }

                    $product->ean = $assignment['gtin'];
                    $product->saveQuietly();
                    $assignedIds[] = $product->id;

                    if ($line->previous_product_id) {
                        Product::onlyTrashed()
                            ->whereKey($line->previous_product_id)
                            ->where('ean', $assignment['gtin'])
                            ->update(['ean' => null]);
                    }

                    $line->update([
                        'product_id' => $product->id,
                        'decision' => $line->kind === Gs1RunLine::KIND_POOL ? Gs1RunLine::DECISION_TOEWIJZEN : $line->decision,
                        'applied_at' => now(),
                    ]);

                    activity()
                        ->performedOn($product)
                        ->causedBy(auth()->user())
                        ->withProperties(['gs1_run_id' => $run->id, 'gtin' => $assignment['gtin'], 'reused' => $assignment['reused']])
                        ->log('gs1: code toegewezen');

                    $assigned++;
                }

                $run->lines()
                    ->where('kind', Gs1RunLine::KIND_WEES)
                    ->where('decision', Gs1RunLine::DECISION_INACTIEF)
                    ->update(['applied_at' => now()]);

                $run->summary = array_merge($run->summary ?? [], [
                    'assigned' => $assigned,
                    'skipped' => $skipped,
                    'placeholders' => $plan->placeholders,
                    'blocked' => $plan->blocked,
                ]);
                $run->status = Gs1Run::STATUS_TOEGEWEZEN;
                $run->save();

                // Binnen de transactie: lukt het bestand niet, dan staat er ook
                // geen toewijzing zonder uploadbestand.
                $this->updater->write($run, $plan);

                return $plan;
            });
        });

        cache()->forget('products_without_ean_count');
        $this->reindex($assignedIds);

        return $plan;
    }

    public function revert(Gs1RunLine $line): bool
    {
        if (! $line->run->isToegewezen()) {
            throw new Gs1RunLockedException(__('Alleen in een toegewezen run kun je regels terugdraaien.'));
        }

        if (! $line->canRevert()) {
            return false;
        }

        $reverted = $this->withLock(fn () => DB::transaction(function () use ($line) {
            // Opnieuw lezen onder het slot: run en regel kunnen intussen
            // afgesloten of al teruggedraaid zijn.
            $run = Gs1Run::query()->whereKey($line->gs1_run_id)->lockForUpdate()->first();
            if (! $run || ! $run->isToegewezen()) {
                throw new Gs1RunLockedException(__('Alleen in een toegewezen run kun je regels terugdraaien.'));
            }

            $fresh = Gs1RunLine::query()->whereKey($line->getKey())->lockForUpdate()->first();
            if (! $fresh || ! $fresh->canRevert()) {
                return false;
            }
            $line->setRawAttributes($fresh->getAttributes(), true);

            Product::withTrashed()
                ->whereKey($line->product_id)
                ->where('ean', $line->gtin)
                ->update(['ean' => null]);

            if ($line->previous_product_id) {
                Product::withTrashed()
                    ->whereKey($line->previous_product_id)
                    ->where(fn ($query) => $query->whereNull('ean')->orWhere('ean', ''))
                    ->update(['ean' => $line->gtin]);
            }

            $line->update(['reverted_at' => now()]);

            $product = Product::withTrashed()->find($line->product_id);
            if ($product) {
                activity()
                    ->performedOn($product)
                    ->causedBy(auth()->user())
                    ->withProperties(['gs1_run_id' => $line->gs1_run_id, 'gtin' => $line->gtin])
                    ->log('gs1: toewijzing teruggedraaid');
            }

            return true;
        }));

        if (! $reverted) {
            return false;
        }

        cache()->forget('products_without_ean_count');
        $this->reindex([$line->product_id]);

        return true;
    }

    public function close(Gs1Run $run): void
    {
        if (! $run->isToegewezen()) {
            throw new Gs1RunLockedException(__('Alleen een toegewezen run kan afgesloten worden.'));
        }

        $run->update(['status' => Gs1Run::STATUS_AFGESLOTEN]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withLock(callable $callback): mixed
    {
        try {
            return Cache::lock(self::LOCK, self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, $callback);
        } catch (LockTimeoutException) {
            throw new Gs1RunLockedException(__('Er loopt al een GS1-toewijzing of terugdraaiing. Probeer het zo opnieuw.'));
        }
    }

    /**
     * De EAN staat in de zoekindex, maar saveQuietly() slaat de listener
     * over die de index bijwerkt. Verwijderde producten staan niet in de
     * index en blijven erbuiten.
     *
     * @param  array<int, int|null>  $productIds
     */
    private function reindex(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter($productIds)));
        if ($productIds === []) {
            return;
        }

        $indexer = app(SearchIndexer::class);
        Product::query()
            ->whereKey($productIds)
            ->with('productGroup')
            ->get()
            ->each(fn (Product $product) => $indexer->index($product));
    }

    private function blockReason(?Product $product, string $gtin): ?string
    {
        if (! $product) {
            return __('Het product bestaat niet meer.');
        }

        if (filled($product->ean) && $product->ean !== $gtin) {
            return __('Het product heeft intussen EAN :ean.', ['ean' => $product->ean]);
        }

        $other = Product::query()->where('ean', $gtin)->whereKeyNot($product->id)->value('id');
        if ($other) {
            return __('De code is intussen in gebruik bij product :id.', ['id' => $other]);
        }

        return null;
    }
}
