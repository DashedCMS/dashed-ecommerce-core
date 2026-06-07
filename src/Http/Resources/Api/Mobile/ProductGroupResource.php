<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $priceFrom = $this->products()->where('current_price', '>', 0)->min('current_price');

        $imageId = $this->firstImage;
        $imageUrl = $imageId ? (mediaHelper()->getSingleMedia($imageId)->url ?? null) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'products_count' => (int) ($this->products_count ?? $this->products()->count()),
            'price_from' => $priceFrom !== null ? (float) $priceFrom : null,
            'image_url' => $imageUrl,
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
