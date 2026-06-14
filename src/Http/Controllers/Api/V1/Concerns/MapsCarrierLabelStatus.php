<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\Concerns;

/**
 * Genormaliseerde verzendlabel-status voor een carrier-rij (Veloyd/MyParcel).
 * Gedeeld door de OrderController (labels-lijst per order) en de verzend-hub
 * (alle zendingen over alle carriers) zodat de status-badges overal identiek zijn.
 */
trait MapsCarrierLabelStatus
{
    /**
     * Genormaliseerde status van een verzendlabel. Gebruikt de door de
     * carrier-sync opgeslagen status (kolom 'status') als die er is; anders
     * afgeleid uit bestaande velden (fout / geprint / concept).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $po  Veloyd-/MyParcelOrder
     * @return array{key: string, label: string, tone: string}
     */
    protected function labelStatus($po): array
    {
        // 'Verzonden/Onderweg/Geleverd' komt UITSLUITEND uit de carrier-sync
        // (kolom 'status'); een track&trace-code bestaat al bij het aanmaken van
        // het label en betekent dus niet dat het pakket al verzonden is. Afgeleid
        // blijft het daarom op geprint/concept tot de vervoerder de status meldt.
        $key = $po->status
            ?: ($po->error ? 'error'
                : ($po->label_printed ? 'printed' : 'concept'));

        $meta = [
            'concept' => ['Concept', 'neutral'],
            'printed' => ['Geprint', 'neutral'],
            'shipped' => ['Verzonden', 'success'],
            'in_transit' => ['Onderweg', 'warning'],
            'pickup' => ['Klaar voor afhalen', 'warning'],
            'delivered' => ['Geleverd', 'success'],
            'returned' => ['Retour', 'warning'],
            'cancelled' => ['Geannuleerd', 'danger'],
            'error' => ['Fout', 'danger'],
        ];
        [$label, $tone] = $meta[(string) $key] ?? ['Onbekend', 'neutral'];

        return ['key' => (string) $key, 'label' => $label, 'tone' => $tone];
    }

    /**
     * De genormaliseerde statussleutels (voor filter-validatie / opties).
     *
     * @return array<int, string>
     */
    protected function labelStatusKeys(): array
    {
        return ['concept', 'printed', 'shipped', 'in_transit', 'pickup', 'delivered', 'returned', 'cancelled', 'error'];
    }

    /**
     * Maakt van het track_and_trace-veld (array of string) een leesbare lijst codes.
     *
     * @return array<int, string>
     */
    protected function trackTraceList($tt): array
    {
        if (blank($tt)) {
            return [];
        }
        if (is_string($tt)) {
            return [$tt];
        }

        $codes = [];
        foreach ((array) $tt as $entry) {
            if (is_array($entry)) {
                foreach ($entry as $code => $url) {
                    $codes[] = (string) $code;
                }
            } elseif (is_string($entry)) {
                $codes[] = $entry;
            }
        }

        return array_values(array_filter($codes, fn ($c) => $c !== ''));
    }
}
