<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'image' => $this->custom_image
                ?: ($this->product && $this->product->firstImage
                    ? (mediaHelper()->getSingleMedia($this->product->firstImage, ['widen' => 200])->url ?? null)
                    : null),
            'quantity' => (int) $this->quantity,
            'returned_quantity' => (int) ($this->returned_quantity ?? 0),
            'price' => $this->price !== null ? (float) $this->price : null,
            'btw' => $this->btw !== null ? (float) $this->btw : null,
            'vat_rate' => $this->vat_rate !== null ? (float) $this->vat_rate : null,
            'discount' => $this->discount !== null ? (float) $this->discount : null,
            'is_pre_order' => (bool) $this->is_pre_order,
            'pre_order_restocked_date' => $this->pre_order_restocked_date,
            'fulfillment_provider' => $this->fulfillment_provider,
            'send_to_fulfiller' => (bool) $this->send_to_fulfiller,
            'added_via' => $this->added_via,
            'extras' => $this->product_extras ?: [],
        ];
    }
}
