<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

class SmartSearch
{
    /**
     * Slim multi-term-zoeken. Splitst de zoekterm op spaties; ELK woord moet ergens
     * matchen over de gegeven kolommen (OR binnen een woord) en ALLE woorden samen
     * (AND). Zo vindt "15cm 4 kinderen" een product waarin die woorden los en in
     * willekeurige volgorde voorkomen. Kolommen mogen ook query-prefixes of
     * JSON-paden zijn (bv. 'op.name', 'name->nl').
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, string>  $columns
     */
    public static function apply($query, ?string $search, array $columns): void
    {
        $search = trim((string) $search);
        if ($search === '' || $columns === []) {
            return;
        }

        $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

        $query->where(function ($outer) use ($terms, $columns): void {
            foreach ($terms as $term) {
                $like = '%' . $term . '%';
                $outer->where(function ($q) use ($like, $columns): void {
                    foreach ($columns as $i => $col) {
                        $i === 0
                            ? $q->where($col, 'like', $like)
                            : $q->orWhere($col, 'like', $like);
                    }
                });
            }
        });
    }
}
