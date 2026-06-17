<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Kortingscode-detail voor de app. Bevat de focus-set velden plus een berekende
 * `is_active`-vlag: nu valt binnen [start_date, end_date] (null = open einde) én
 * de voorraad is niet uitgeput.
 */
class DiscountCodeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'type' => $this->type,
            'discount_percentage' => $this->discount_percentage !== null ? (float) $this->discount_percentage : null,
            'discount_amount' => $this->discount_amount !== null ? (float) $this->discount_amount : null,
            'start_date' => optional($this->start_date)->toIso8601String(),
            'end_date' => optional($this->end_date)->toIso8601String(),
            'use_stock' => (bool) $this->use_stock,
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'stock_used' => (int) $this->stock_used,
            'is_active' => $this->isActive(),
        ];
    }

    private function isActive(): bool
    {
        $now = Carbon::now();

        if ($this->start_date && $this->start_date->gt($now)) {
            return false;
        }

        if ($this->end_date && $this->end_date->lt($now)) {
            return false;
        }

        if ($this->use_stock && $this->stock !== null && (int) $this->stock_used >= (int) $this->stock) {
            return false;
        }

        return true;
    }
}
