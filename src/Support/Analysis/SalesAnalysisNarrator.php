<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Throwable;
use Dashed\DashedAi\Facades\Ai;

/**
 * Schrijft de leeswijzer boven de analyse.
 *
 * Het model krijgt alleen de signalen, nooit ruwe orders. Daardoor hoeft het
 * niet te rekenen en is elk getal in het rapport door code bepaald; de AI
 * voegt taal en volgorde toe, verder niets.
 *
 * Alles wat misgaat levert null op. De pagina toont dan de signalen zelf, op
 * ernst gesorteerd. De verteller is een toevoeging, nooit een voorwaarde.
 */
class SalesAnalysisNarrator
{
    public static function narrate(SalesAnalysisReport $report, AnalysisContext $context): ?string
    {
        $signals = $report->signals();

        if ($signals === []) {
            return null;
        }

        try {
            if (! class_exists(Ai::class) || ! Ai::hasProvider()) {
                return null;
            }

            $narrative = Ai::text(self::prompt($signals, $context));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if (! is_string($narrative) || trim($narrative) === '') {
            return null;
        }

        return trim($narrative);
    }

    /** @param  array<int, Signal>  $signals */
    protected static function prompt(array $signals, AnalysisContext $context): string
    {
        $lines = [];

        foreach ($signals as $signal) {
            $numbers = [];
            foreach ($signal->numbers as $label => $value) {
                $numbers[] = "{$label}: {$value}";
            }

            $lines[] = '- [' . $signal->severity . '] ' . $signal->title . '. ' . $signal->explanation
                . ($numbers === [] ? '' : ' (' . implode(', ', $numbers) . ')');
        }

        return implode("\n", [
            'Je schrijft de leeswijzer boven een verkoopanalyse van een webshop.',
            'Periode: ' . $context->period->start->toDateString() . ' tot en met ' . $context->period->end->toDateString() . '.',
            '',
            'Hieronder staan de bevindingen die de analyse heeft berekend. Ze zijn juist; neem ze over en reken zelf niets uit.',
            '',
            implode("\n", $lines),
            '',
            'Schrijf maximaal drie korte alinea\'s in het Nederlands, zakelijk en concreet.',
            'Begin met wat het meest opvalt. Benoem welke twee of drie dingen als eerste opgepakt zouden moeten worden en waarom.',
            'Leg verbanden tussen bevindingen waar die er zijn. Verzin geen cijfers die er niet staan.',
            'Gebruik geen gedachtestreepjes.',
        ]);
    }
}
