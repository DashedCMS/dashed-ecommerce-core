<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * POS-orders kregen de verzend-BTW niet in het header-btw-veld:
     * calculatePosCartTotals() berekent 'vat' enkel over de productregels,
     * terwijl de aparte verzendregel wél 21% btw draagt en in order->total zit.
     * Daardoor rapporteert order->btw (en de BTW-export/verzamelfactuur) te weinig.
     *
     * Deze migratie trekt de header-btw voor bestaande POS-orders recht op basis
     * van de som van de regel-btw's (die zijn de bron van waarheid: elke regel,
     * incl. verzending, draagt zijn eigen btw). vat_percentages wordt navenant
     * opnieuw opgebouwd per tarief.
     *
     * Guard: oude kortingsorders waarbij een lump-korting buiten de regels om is
     * toegepast (order->total < som regelprijzen, terwijl de regels zelf geen
     * korting dragen) worden overgeslagen — daar is de header-btw juist al correct
     * berekend over het gekorte totaal en zou de som van de regel-btw's (pre-korting)
     * te hoog uitkomen. Reverse-charge-orders blijven eveneens ongemoeid.
     */
    public function up(): void
    {
        DB::table('dashed__orders')
            ->where('order_origin', 'pos')
            ->orderBy('id')
            ->chunkById(300, function ($orders) {
                foreach ($orders as $order) {
                    if ($order->vat_reverse_charge) {
                        continue;
                    }

                    $lines = DB::table('dashed__order_products')
                        ->where('order_id', $order->id)
                        ->get(['btw', 'price', 'discount', 'vat_rate']);

                    if ($lines->isEmpty()) {
                        continue;
                    }

                    $sumBtw = round((float) $lines->sum('btw'), 2);
                    $sumPrice = round((float) $lines->sum('price'), 2);
                    $sumDiscount = round((float) $lines->sum('discount'), 2);

                    // Lump-korting buiten de regels om -> header al correct, niet aanraken.
                    $hasLumpDiscount = ((float) $order->total + 0.01 < $sumPrice)
                        && $sumDiscount < 0.01
                        && (float) $order->discount > 0.01;
                    if ($hasLumpDiscount) {
                        continue;
                    }

                    if (abs($sumBtw - (float) $order->btw) < 0.02) {
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
        // Geen rollback: dit is een eenmalige data-correctie van foutieve btw-waarden.
    }
};
