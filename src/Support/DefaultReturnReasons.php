<?php

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedEcommerceCore\Models\ReturnReason;

class DefaultReturnReasons
{
    /**
     * @return array<int, array{nl: string, en: string}>
     */
    public static function defaults(): array
    {
        return [
            ['nl' => 'Te klein', 'en' => 'Too small'],
            ['nl' => 'Te groot', 'en' => 'Too large'],
            ['nl' => 'Beschadigd ontvangen', 'en' => 'Arrived damaged'],
            ['nl' => 'Voldoet niet aan verwachting', 'en' => 'Not as expected'],
            ['nl' => 'Verkeerd product ontvangen', 'en' => 'Wrong product received'],
            ['nl' => 'Anders', 'en' => 'Other'],
        ];
    }

    public static function seed(): void
    {
        // Load all existing NL labels in one query to avoid per-row JSON where-clauses,
        // which are unreliable across SQLite versions (used in tests) and MySQL alike.
        // A single in-PHP set-lookup is safe and equally efficient for a small seed set.
        $existingNlLabels = ReturnReason::query()
            ->get()
            ->map(fn ($r) => $r->getTranslation('label', 'nl'))
            ->flip()
            ->all();

        foreach (self::defaults() as $index => $labels) {
            if (isset($existingNlLabels[$labels['nl']])) {
                continue;
            }

            $reason = new ReturnReason();
            $reason->setTranslation('label', 'nl', $labels['nl']);
            $reason->setTranslation('label', 'en', $labels['en']);
            $reason->sort_order = $index;
            $reason->is_active = true;
            $reason->save();
        }
    }
}
