<?php

namespace Dashed\DashedEcommerceCore\Services\ProductFinder;

use Throwable;
use Dashed\DashedAi\Facades\Ai;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFinder;

class ProductFinderMatcher
{
    private const CANDIDATE_LIMIT = 40;

    private const DEFAULT_RESULT_COUNT = 4;

    /**
     * @param  array<string, string>  $answers  vraaglabel => gekozen antwoord
     * @return array<int, array{product: Product, reason: string}>
     */
    public function match(ProductFinder $finder, array $answers): array
    {
        return $this->rank($finder, $answers, $this->candidates($finder));
    }

    /**
     * @param  array<string, string>  $answers
     * @param  Collection<int, Product>  $candidates
     * @return array<int, array{product: Product, reason: string}>
     */
    public function rank(ProductFinder $finder, array $answers, Collection $candidates): array
    {
        $limit = $this->resultLimit($finder);

        if ($candidates->isEmpty()) {
            return [];
        }

        if (! Ai::hasProvider()) {
            return $this->fallback($candidates, $limit);
        }

        try {
            $response = Ai::json($this->buildPrompt($finder, $answers, $candidates));
        } catch (Throwable $e) {
            report($e);

            return $this->fallback($candidates, $limit);
        }

        $rows = is_array($response) ? ($response['results'] ?? null) : null;
        if (! is_array($rows) || $rows === []) {
            return $this->fallback($candidates, $limit);
        }

        $byId = $candidates->keyBy(fn (Product $p) => (int) $p->id);
        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if (! $byId->has($id)) {
                continue;
            }
            $out[] = ['product' => $byId->get($id), 'reason' => trim((string) ($row['reason'] ?? ''))];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out !== [] ? $out : $this->fallback($candidates, $limit);
    }

    private function resultLimit(ProductFinder $finder): int
    {
        return max(1, (int) ($finder->result_count ?: self::DEFAULT_RESULT_COUNT));
    }

    /**
     * @param  Collection<int, Product>  $candidates
     * @return array<int, array{product: Product, reason: string}>
     */
    private function fallback(Collection $candidates, int $limit): array
    {
        return $candidates->take($limit)
            ->map(fn (Product $p) => ['product' => $p, 'reason' => ''])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $answers
     * @param  Collection<int, Product>  $candidates
     */
    private function buildPrompt(ProductFinder $finder, array $answers, Collection $candidates): string
    {
        $profile = collect($answers)
            ->map(fn ($answer, $question) => "- {$question}: {$answer}")
            ->implode("\n");

        $products = $candidates->map(fn (Product $p) => [
            'id' => (int) $p->id,
            'name' => (string) $p->name,
            'price' => (float) ($p->current_price ?? $p->price ?? 0),
        ])->values()->all();

        $productsJson = json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $limit = $this->resultLimit($finder);

        return <<<PROMPT
            Je bent een behulpzame productadviseur. Op basis van de antwoorden van de klant,
            kies de {$limit} best passende producten uit de lijst. Geef per product één korte
            reden (Nederlands) waarom het past. Gebruik uitsluitend producten uit de lijst.

            Antwoorden van de klant:
            {$profile}

            Beschikbare producten (JSON):
            {$productsJson}

            Antwoord met JSON in exact deze vorm:
            {"results": [{"id": <product-id>, "reason": "<korte reden>"}]}
            PROMPT;
    }

    /**
     * @return Collection<int, Product>
     */
    protected function candidates(ProductFinder $finder): Collection
    {
        $query = Product::query()->where('public', 1);

        $activeSite = Sites::getActive();
        if ($activeSite) {
            $query->whereJsonContains('site_ids', $activeSite);
        }

        if ($finder->only_in_stock) {
            $query->where('in_stock', 1);
        }

        $categoryIds = array_filter((array) ($finder->category_ids ?? []));
        if ($categoryIds !== []) {
            $query->whereHas('productCategories', function ($q) use ($categoryIds) {
                $q->whereIn('dashed__product_categories.id', $categoryIds);
            });
        }

        return $query->limit(self::CANDIDATE_LIMIT)->get();
    }
}
