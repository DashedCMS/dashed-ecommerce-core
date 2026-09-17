<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Gs1Run;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Gs1RunLine;
use Dashed\DashedEcommerceCore\ValueObjects\Gs1Row;

/**
 * Maakt van een upload de regels van een run. Schrijft niets naar
 * producten. Volgorde per GTIN: eerst op naam koppelen, dan kijken of de
 * code bij een levend product hoort, dan de status.
 */
class Gs1RunAnalyzer
{
    public function __construct(
        private readonly Gs1FileReader $reader,
        private readonly Gs1NameMatcher $matcher,
    ) {
    }

    public function analyze(Gs1Run $run): void
    {
        if (! $run->isConcept()) {
            throw new Gs1RunLockedException(__('Alleen een run in concept kan opnieuw geanalyseerd worden.'));
        }

        $path = Storage::disk('local')->path($run->file_path);
        $contents = $this->reader->read($path);

        /** @var array<string, array<int, Gs1Row>> $groups */
        $groups = [];
        foreach ($contents->rows as $rowNumber => $row) {
            if ($row->hasRealGtin()) {
                $groups[$row->gtin][$rowNumber] = $row;
            }
        }

        $owners = $this->owners(array_map('strval', array_keys($groups)));
        $nameIndex = $this->matcher->index(Product::query()->needsGs1Code());
        $claimed = [];
        $counts = array_fill_keys([
            Gs1RunLine::KIND_NAAM_MATCH, Gs1RunLine::KIND_POOL, Gs1RunLine::KIND_WEES, Gs1RunLine::KIND_AL_GEKOPPELD, 'data_source',
        ], 0);
        $lines = [];
        $now = now();

        foreach ($groups as $gtin => $rows) {
            $gtin = (string) $gtin;

            if (collect($rows)->contains(fn (Gs1Row $row) => $row->createdInDataSource)) {
                $counts['data_source']++;

                continue;
            }

            /** @var Gs1Row $first */
            $first = reset($rows);
            $productsWithCode = $owners->get($gtin, collect());
            $live = $productsWithCode->first(fn ($product) => $product->deleted_at === null);
            $trashed = $productsWithCode->first(fn ($product) => $product->deleted_at !== null);
            $matchId = $first->description ? ($nameIndex[Gs1NameMatcher::normalize($first->description)] ?? null) : null;

            $line = [
                'gs1_run_id' => $run->id,
                'gtin' => $gtin,
                'sheet_rows' => json_encode(array_keys($rows)),
                'gs1_status' => $first->status,
                'gs1_description' => $first->description ? mb_substr($first->description, 0, 500) : null,
                'kind' => null,
                'decision' => null,
                'product_id' => null,
                'previous_product_id' => $trashed?->id,
                'reused_inactive' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (! $live && $matchId && ! isset($claimed[$matchId])) {
                $claimed[$matchId] = true;
                $line = array_merge($line, ['kind' => Gs1RunLine::KIND_NAAM_MATCH, 'decision' => Gs1RunLine::DECISION_TOEWIJZEN, 'product_id' => $matchId]);
            } elseif ($live && $first->isActive()) {
                continue;
            } elseif ($live) {
                $line = array_merge($line, ['kind' => Gs1RunLine::KIND_AL_GEKOPPELD, 'product_id' => $live->id]);
            } elseif ($first->isActive()) {
                $line = array_merge($line, ['kind' => Gs1RunLine::KIND_WEES, 'decision' => Gs1RunLine::DECISION_LATEN]);
            } elseif ($first->isConcept() || $first->isInactive()) {
                $line = array_merge($line, ['kind' => Gs1RunLine::KIND_POOL, 'reused_inactive' => $first->isInactive()]);
            } else {
                continue;
            }

            $counts[$line['kind']]++;
            $lines[] = $line;
        }

        $run->lines()->delete();
        foreach (array_chunk($lines, 500) as $chunk) {
            Gs1RunLine::insert($chunk);
        }

        $run->fill([
            'contract_sheet' => $contents->contractSheetName,
            'reference_data' => Gs1ReferenceData::fromFile($path)->toArray(),
            'summary' => $counts + ['gtins' => count($groups)],
        ])->save();
    }

    /**
     * @param  list<string>  $gtins
     * @return Collection<string, Collection<int, Product>>
     */
    private function owners(array $gtins): Collection
    {
        $products = collect();
        foreach (array_chunk($gtins, 500) as $chunk) {
            $products = $products->concat(
                Product::withTrashed()->whereIn('ean', $chunk)->get(['id', 'ean', 'deleted_at'])
            );
        }

        return $products->groupBy(fn (Product $product) => (string) $product->ean);
    }
}
