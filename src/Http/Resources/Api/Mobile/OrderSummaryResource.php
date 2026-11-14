<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status,
            'fulfillment_status' => $this->fulfillment_status,
            'is_priority' => (bool) $this->is_priority,
            'ship_by_date' => $this->ship_by_date?->toDateString(),
            'deliver_by_date' => $this->deliver_by_date?->toDateString(),
            'deadline_source' => $this->deadline_source,
            'deadline_label' => $this->deadlineLabel(),
            'deadline_urgency' => $this->deadlineUrgency(),
            'assigned_user' => $this->assigned_to_user_id
                ? ['id' => (int) $this->assigned_to_user_id, 'name' => optional($this->assignedUser)->name]
                : null,
            'total' => $this->total !== null ? (float) $this->total : null,
            'customer_name' => trim((string) ($this->first_name . ' ' . $this->last_name)) ?: $this->email,
            'created_at' => optional($this->created_at)->toIso8601String(),
            // Bestelde producten (naam × aantal) voor de inline-preview in de lijst.
            'products' => $this->orderProducts->map(fn ($p) => [
                'name' => (string) $p->name,
                'quantity' => (int) $p->quantity,
            ])->values()->all(),
        ];
    }
}
