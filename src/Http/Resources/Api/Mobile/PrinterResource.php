<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Dashed\DashedEcommerceCore\Models\Printer
 */
class PrinterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cups_name' => $this->cups_name,
            'type' => $this->type?->value,
            'type_label' => $this->type?->label(),
            'location' => $this->location,
            'is_active' => (bool) $this->is_active,
            'is_online' => $this->isOnline(),
            'pending_jobs' => $this->pendingJobsCount(),
            'last_ping_at' => $this->last_ping_at?->toIso8601String(),
            'is_paired' => (bool) $this->plain_token,
        ];
    }
}
