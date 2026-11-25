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
            // Was deze regel op voorraad op besteldatum? 'in_stock' / 'out_of_stock'
            // / null (onbekend of geen voorraadbeheer; de app toont null niet).
            'stock_status_at_order' => $this->stock_status_at_order,
            // Huidige voorraad van het product (voorraad min gereserveerd), null zonder voorraadbeheer.
            'stock_quantity' => \Dashed\DashedEcommerceCore\Classes\OrderProductStock::currentQuantity($this->resource),
            'fulfillment_provider' => $this->fulfillment_provider,
            'send_to_fulfiller' => (bool) $this->send_to_fulfiller,
            'added_via' => $this->added_via,
            'extras' => collect($this->product_extras ?: [])->map(function ($extra): array {
                $row = [
                    'name' => $extra['name'] ?? null,
                    'value' => $extra['value'] ?? null,
                ];
                // Door de klant geüpload bestand/foto: geef een (tijdelijke) URL mee
                // zodat de app 'm kan openen, net als de "download"-link in het CMS.
                if (! empty($extra['path'])) {
                    $row['url'] = \Dashed\DashedEcommerceCore\Classes\ProductExtraFile::adminUrl((string) $extra['path']);
                }

                return $row;
            })->all(),
        ];
    }
}
