<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Twee losse data-correcties die samen de BTW-uitsplitsing in de
     * verzamelfactuur/BTW-export laten kloppen (ex-btw × tarief == btw):
     *
     * 1) Etsy-orders kregen order->total uit Etsy's 'grandtotal', dat de door
     *    Etsy zelf geïnde marketplace-BTW (bv. UK/US) bevat. Die BTW is niet
     *    onze omzet en zit niet in de orderregels, waardoor order->total hoger
     *    stond dan de som van de regels. De export leidt ex-btw af als
     *    total - btw en telde die vreemde marketplace-BTW ten onrechte als omzet.
     *    -> total wordt teruggezet naar de som van de eigen orderregels.
     *
     * 2) order->btw werd per order opgebouwd uit de som van (per regel afgeronde)
     *    regel-btw's. Over veel orders drift die som weg van de btw over het
     *    bruto totaal, waardoor de export-uitsplitsing niet meer op het tarief
     *    uitkomt. -> btw wordt per order herberekend als de BTW uit het bruto
     *    totaal: round(total * 21 / 121). Dat is robuust voor zowel regel- als
     *    lump-kortingen (de BTW rekent altijd over het daadwerkelijk betaalde
     *    totaal) en minimaliseert de afrondingsdrift op zaakniveau.
     *
     * Scope: uitsluitend orders die in de statistieken/exports meetellen
     * (geen PROFORMA/RETURN, niet geannuleerd), met verlegde BTW uitgesloten en
     * enkel orders met precies één tarief van 21%. Orders met 9%, buitenlandse
     * OSS-tarieven of gemengde tarieven blijven ongemoeid.
     */
    public function up(): void
    {
        // --- Stap 1: Etsy-totalen terugzetten naar de som van de eigen regels ---
        DB::table('dashed__orders')
            ->where('order_origin', 'etsy')
            ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->chunkById(300, function ($orders) {
                foreach ($orders as $order) {
                    $sumPrice = round(
                        (float) DB::table('dashed__order_products')
                            ->where('order_id', $order->id)
                            ->sum('price'),
                        2
                    );

                    // Alleen ingrijpen wanneer het totaal hoger staat dan de regels
                    // (= door Etsy geïnde marktplaats-BTW). Lager totaal duidt op een
                    // legitieme korting en blijft ongemoeid.
                    if ($sumPrice <= 0 || (float) $order->total <= $sumPrice + 0.01) {
                        continue;
                    }

                    DB::table('dashed__orders')
                        ->where('id', $order->id)
                        ->update(['total' => $sumPrice]);
                }
            });

        // --- Stap 2: header-btw herberekenen uit het bruto totaal (21%) ---
        DB::table('dashed__orders')
            ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('id')
            ->chunkById(300, function ($orders) {
                foreach ($orders as $order) {
                    if ($order->vat_reverse_charge) {
                        continue;
                    }

                    $rates = $this->nonZeroVatRates($order->vat_percentages);

                    // Enkel zuivere 21%-orders: precies één tarief en dat is 21%.
                    if ($rates !== ['21']) {
                        continue;
                    }

                    // vat_percentages bevat geen 0%-regels; controleer de regels zelf.
                    // Orders met een niet-21%-regel (bv. 0% margeregeling) mogen niet
                    // volledig als 21% worden herberekend — daar is de som van de
                    // regel-btw's leidend en blijft de header ongemoeid.
                    $hasNon21Line = DB::table('dashed__order_products')
                        ->where('order_id', $order->id)
                        ->whereNotNull('vat_rate')
                        ->whereRaw('ROUND(vat_rate) <> 21')
                        ->where('price', '<>', 0)
                        ->exists();
                    if ($hasNon21Line) {
                        continue;
                    }

                    $newBtw = round((float) $order->total * 21 / 121, 2);

                    // Vergelijk op hele centen (integer) i.p.v. floats: een verschil
                    // van precies 1 cent moet worden doorgevoerd, en float-onnauwkeurig-
                    // heid (7.43 - 7.42 == 0.00999…) mag zo'n update niet overslaan.
                    if ((int) round($newBtw * 100) === (int) round((float) $order->btw * 100)) {
                        continue;
                    }

                    DB::table('dashed__orders')
                        ->where('id', $order->id)
                        ->update([
                            'btw' => $newBtw,
                            'vat_percentages' => json_encode((object) ['21' => $newBtw]),
                        ]);
                }
            });
    }

    /**
     * Geeft de BTW-tarieven (als string-keys) met een niet-nul bedrag terug.
     *
     * @return array<int, string>
     */
    private function nonZeroVatRates(mixed $vatPercentages): array
    {
        $decoded = is_string($vatPercentages)
            ? json_decode($vatPercentages, true)
            : $vatPercentages;

        if (! is_array($decoded)) {
            return [];
        }

        $rates = [];
        foreach ($decoded as $rate => $amount) {
            if (abs((float) $amount) >= 0.01) {
                $rates[] = (string) (int) $rate;
            }
        }
        sort($rates);

        return $rates;
    }

    public function down(): void
    {
        // Geen rollback: eenmalige data-correctie van foutieve totalen/btw-waarden.
    }
};
