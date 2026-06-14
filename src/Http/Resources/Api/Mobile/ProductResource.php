<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imageId = $this->firstImage;
        $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId)->url ?? null) : null;

        // Volledige fotogalerij (id + url) zodat de app bestaande foto's kan
        // tonen en herordenen/verwijderen bij het bewerken van een product.
        $images = [];
        foreach ((is_array($this->images) ? $this->images : []) as $id) {
            $url = mediaHelper()->getSingleMedia($id)->url ?? null;
            if ($url) {
                $images[] = ['id' => (int) $id, 'url' => $url];
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'new_price' => $this->new_price !== null ? (float) $this->new_price : null,
            'price' => $this->price !== null ? (float) $this->price : null,
            'current_price' => $this->price !== null ? (float) $this->price : null,
            'public' => (bool) $this->public,
            'image_url' => $imageUrl,
            'images' => $images,
            'product_group_id' => $this->product_group_id !== null ? (int) $this->product_group_id : null,
            'category_ids' => $this->productCategories->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'total_purchases' => (int) ($this->total_purchases ?? 0),

            // Voorraad
            'use_stock' => (bool) $this->use_stock,
            'stock' => $this->stock !== null ? (int) $this->stock : null,
            'low_stock_notification' => (bool) $this->low_stock_notification,
            'low_stock_notification_limit' => $this->low_stock_notification_limit !== null ? (int) $this->low_stock_notification_limit : null,
            'out_of_stock_sellable' => (bool) $this->out_of_stock_sellable,
            'expected_in_stock_date' => optional($this->expected_in_stock_date)->format('Y-m-d'),
            'expected_delivery_in_days' => $this->expected_delivery_in_days !== null ? (int) $this->expected_delivery_in_days : null,
            'stock_status' => $this->stock_status,
            'limit_purchases_per_customer' => (bool) $this->limit_purchases_per_customer,
            'limit_purchases_per_customer_limit' => $this->limit_purchases_per_customer_limit !== null ? (int) $this->limit_purchases_per_customer_limit : null,

            // Praktische informatie
            'purchase_price' => $this->purchase_price !== null ? (float) $this->purchase_price : null,
            'vat_rate' => $this->vat_rate !== null ? (float) $this->vat_rate : null,
            'sku' => $this->sku,
            'ean' => $this->ean,
            'article_code' => $this->article_code,
            'weight' => $this->weight !== null ? (int) $this->weight : null,
            'length' => $this->length !== null ? (int) $this->length : null,
            'width' => $this->width !== null ? (int) $this->width : null,
            'height' => $this->height !== null ? (int) $this->height : null,
        ];
    }
}
