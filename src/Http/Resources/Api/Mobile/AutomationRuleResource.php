<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Eén automatiseringsregel voor de app-lijst. De regel-inhoud (voorwaarden en
 * acties) bouw je in het CMS; de app toont alleen een samenvatting plus de
 * aan/uit-schakelaar, dus `actions` gaat als aantal mee, niet als payload.
 */
class AutomationRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'trigger' => $this->trigger,
            'trigger_label' => $this->triggerLabel(),
            'is_active' => (bool) $this->is_active,
            'last_run_at' => $this->lastRunAt(),
            'actions_count' => count($this->actions ?? []),
        ];
    }

    /**
     * Het leesbare label uit de trigger-registry. Een regel kan naar een trigger
     * wijzen die niet (meer) geregistreerd is — bijvoorbeeld omdat het package
     * dat hem aanleverde uit staat. Dan tonen we de key zelf: liever een ruwe
     * key in de app dan een lijst die helemaal niet laadt.
     */
    private function triggerLabel(): string
    {
        $key = (string) $this->trigger;
        $trigger = app(MobileApiRegistry::class)->automationTrigger($key);

        return (string) ($trigger['label'] ?? $key);
    }

    /**
     * Wanneer deze regel voor het laatst liep. Komt uit `withMax('runs', ...)`
     * op de query, zodat de lijst geen N+1 oplevert.
     */
    private function lastRunAt(): ?string
    {
        $value = $this->runs_max_created_at ?? null;

        if ($value === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->toIso8601String();
    }
}
