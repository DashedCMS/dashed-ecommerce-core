<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Dashed\DashedEcommerceCore\Classes\ParcelType;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblemDetector;

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
            // On hold: mag (nog) niet uitgeleverd worden (zie Order::putOnHold).
            'on_hold' => $this->on_hold_at !== null,
            'on_hold_reason' => $this->on_hold_reason,
            'ship_by_date' => $this->ship_by_date?->toDateString(),
            'deliver_by_date' => $this->deliver_by_date?->toDateString(),
            'deadline_source' => $this->deadline_source,
            'deadline_label' => $this->deadlineLabel(),
            'deadline_urgency' => $this->deadlineUrgency(),
            'assigned_user' => $this->assigned_to_user_id
                ? ['id' => (int) $this->assigned_to_user_id, 'name' => optional($this->assignedUser)->name]
                : null,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'total' => $this->total !== null ? (float) $this->total : null,
            'customer_name' => trim((string) ($this->first_name . ' ' . $this->last_name)) ?: $this->email,
            // Uit de withExists-subqueries in OrderController::index(); false als niet geladen
            // of als de order al is afgehandeld/verzonden (zelfde regel als de scope).
            'has_label_problem' => (bool) (($this->lp_veloyd ?? false) || ($this->lp_myparcel ?? false))
                && ! LabelProblemDetector::isDone($this->fulfillment_status),
            // Verzendvorm (Pakket, Brievenbus, Ophalen, ...), see ParcelType.
            'parcel_type' => OrderResource::parcelTypePayload(ParcelType::forOrder($this->resource)),
            'created_at' => optional($this->created_at)->toIso8601String(),
            // Bestelde producten (naam × aantal) voor de inline-preview in de lijst.
            'products' => $this->orderProducts->map(fn ($p) => [
                'name' => (string) $p->name,
                'quantity' => (int) $p->quantity,
            ])->values()->all(),
            // Bijbestellingen: add_on_orders_count (parent) en add_on_for (de bijbestelling zelf).
            'add_on' => Display::apiPayload($this->resource, false),
        ];
    }
}
