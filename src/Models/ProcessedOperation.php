<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Idempotentie-logboek voor offline ingevoerde app-acties. Elke client-actie
 * draagt een uniek `op_id`; replay'et de app de actie na een sync nog eens,
 * dan herkennen we het op_id en passen we de mutatie niet opnieuw toe.
 */
class ProcessedOperation extends Model
{
    protected $table = 'dashed__processed_operations';

    protected $fillable = [
        'op_id',
        'result_summary',
    ];

    protected $casts = [
        'result_summary' => 'array',
    ];

    /**
     * Voer `$apply` precies één keer uit voor dit `$opId`. Is het op_id al
     * verwerkt, dan wordt `$apply` NIET aangeroepen en geeft deze methode het
     * eerder bewaarde resultaat-overzicht terug (dedup). Anders draait `$apply`
     * binnen een transactie en bewaren we de teruggegeven samenvatting.
     *
     * Zonder op_id (null/'') is er geen idempotentie: `$apply` draait gewoon en
     * er wordt niets gelogd — bestaande callers gedragen zich exact als vroeger.
     *
     * @param  Closure():array<string, mixed>  $apply  geeft de result-summary terug
     * @return array{replayed: bool, result_summary: array<string, mixed>|null}
     */
    public static function once(?string $opId, Closure $apply): array
    {
        if ($opId === null || $opId === '') {
            return ['replayed' => false, 'result_summary' => $apply()];
        }

        return DB::transaction(function () use ($opId, $apply): array {
            // Vergrendel het bestaande logboek-record (indien aanwezig) zodat
            // twee gelijktijdige replays elkaar niet kunnen passeren.
            $existing = static::query()
                ->where('op_id', $opId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return ['replayed' => true, 'result_summary' => $existing->result_summary];
            }

            $summary = $apply();

            static::create([
                'op_id' => $opId,
                'result_summary' => $summary,
            ]);

            return ['replayed' => false, 'result_summary' => $summary];
        });
    }
}
