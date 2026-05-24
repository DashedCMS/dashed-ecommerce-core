<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type->value,
            'order_invoice_id' => $this->order?->invoice_id,
            'pdf_url' => url("/api/print/{$this->ulid}/pdf"),
        ];
    }
}
