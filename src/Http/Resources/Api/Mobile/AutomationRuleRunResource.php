<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eén regel uit het uitvoerlog. `status` gaat ongewijzigd mee — naast
 * success/failed bestaat 'running' (een geclaimde, nog lopende run), en de app
 * moet die als "bezig" kunnen tonen in plaats van als uitkomst.
 */
class AutomationRuleRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_id' => $this->rule_id,
            // Een run overleeft zijn regel niet noodzakelijk; zonder regel
            // tonen we niets in plaats van te crashen op ->name.
            'rule_name' => $this->rule?->name,
            'trigger' => $this->trigger,
            'status' => $this->status,
            'results' => $this->results ?? [],
            'error' => $this->error,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'subject' => $this->subjectPayload(),
        ];
    }

    /**
     * Het onderwerp waarop de regel losging. In fase 1 is dat altijd een Order,
     * maar het veld is morph, dus we vallen netjes terug op de klassenaam voor
     * een toekomstig ander onderwerp.
     *
     * @return array{type: string, id: int|string|null, label: string}
     */
    private function subjectPayload(): array
    {
        $subject = $this->subject;

        return [
            'type' => $this->subjectType(),
            'id' => $this->subject_id,
            'label' => $this->subjectLabel($subject),
        ];
    }

    private function subjectType(): string
    {
        $type = (string) $this->subject_type;

        // Zonder morph-map staat hier de FQCN; de app wil een korte sleutel.
        return match ($type) {
            Order::class, 'order' => 'order',
            default => strtolower(class_basename($type)),
        };
    }

    private function subjectLabel(?Model $subject): string
    {
        if ($subject instanceof Order) {
            $invoiceId = trim((string) $subject->invoice_id);

            return $invoiceId !== '' ? $invoiceId : "Bestelling #{$subject->id}";
        }

        // Verwijderd onderwerp of een type dat we nog niet kennen: toon in elk
        // geval iets herkenbaars in plaats van een lege cel.
        return $this->subjectType() . ' #' . $this->subject_id;
    }
}
