<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Dashed\DashedEcommerceCore\Models\OrderReturn */
class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->order;
        $customerName = trim(($order->first_name ?? '') . ' ' . ($order->last_name ?? '')) ?: null;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'order' => [
                'id' => $this->order_id,
                'invoice_id' => $order?->invoice_id,
                'customer_name' => $customerName,
            ],
            'email' => $this->email,
            'customer_note' => $this->customer_note,
            'admin_note' => $this->admin_note,
            'rejected_reason' => $this->rejected_reason,
            'auto_accepted' => (bool) $this->auto_accepted,
            'label_provider' => $this->return_label_provider,
            'has_label' => $this->return_label_path
                ? Storage::disk('public')->exists($this->return_label_path)
                : false,
            'requested_at' => optional($this->requested_at)->toIso8601String(),
            'approved_at' => optional($this->approved_at)->toIso8601String(),
            'rejected_at' => optional($this->rejected_at)->toIso8601String(),
            'handled_at' => optional($this->handled_at)->toIso8601String(),
            'lines' => $this->lines->map(function ($line): array {
                $reason = $line->returnReason;
                $reasonLabel = null;
                if ($reason) {
                    $label = $reason->label;
                    $reasonLabel = is_array($label)
                        ? ($label[app()->getLocale()] ?? reset($label) ?: null)
                        : $label;
                }

                return [
                    'id' => $line->id,
                    'product_name' => $line->orderProduct?->name ?? '—',
                    'quantity' => (int) $line->quantity,
                    'reason_label' => $reasonLabel,
                    'reason_note' => $line->reason_note,
                ];
            })->values()->all(),
        ];
    }
}
