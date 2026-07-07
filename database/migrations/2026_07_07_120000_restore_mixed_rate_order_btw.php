<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Herstel voor orders met gemengde btw-tarieven.
     *
     * Een eerdere versie van 2026_07_07_100000 herberekende de header-btw als
     * round(total * 21 / 121) voor elke order waarvan vat_percentages enkel een
     * 21%-sleutel had. Bij orders met óók een 0%-regel (bv. margeregeling) telt
     * die 0%-regel niet mee in vat_percentages, waardoor de volledige order
     * onterecht als 21% werd gerekend (bv. order 263: 12,28 i.p.v. 1,21).
     *
     * Deze migratie zet voor orders met minstens één niet-21%-regel de header-btw
     * en vat_percentages terug op de som van de regel-btw's — de juiste btw per
     * tarief. Idempotent; raakt geen zuivere 21%-orders of verlegde orders.
     */
    public function up(): void
    {
        DB::table('dashed__orders')
            ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])
            ->where('status', '!=', 'cancelled')
            ->where('vat_reverse_charge', false)
            ->orderBy('id')
            ->chunkById(300, function ($orders) {
                foreach ($orders as $order) {
                    $lines = DB::table('dashed__order_products')
                        ->where('order_id', $order->id)
                        ->get(['btw', 'price', 'vat_rate']);

                    if ($lines->isEmpty()) {
                        continue;
                    }

                    // Alleen orders met een niet-21%-regel (met omzet) zijn gemengd.
                    $hasNon21Line = $lines->contains(function ($line) {
                        return $line->vat_rate !== null
                            && (int) round((float) $line->vat_rate) !== 21
                            && abs((float) $line->price) >= 0.01;
                    });
                    if (! $hasNon21Line) {
                        continue;
                    }

                    $sumBtw = round((float) $lines->sum('btw'), 2);

                    if ((int) round($sumBtw * 100) === (int) round((float) $order->btw * 100)) {
                        continue;
                    }

                    $vatPercentages = [];
                    foreach ($lines as $line) {
                        $rate = (string) (int) round((float) $line->vat_rate);
                        $vatPercentages[$rate] = round(($vatPercentages[$rate] ?? 0) + (float) $line->btw, 2);
                    }
                    $vatPercentages = array_filter($vatPercentages, fn ($amount) => abs($amount) >= 0.01);

                    DB::table('dashed__orders')
                        ->where('id', $order->id)
                        ->update([
                            'btw' => $sumBtw,
                            'vat_percentages' => json_encode((object) $vatPercentages),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Geen rollback: eenmalige data-correctie.
    }
};
