<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Volledige bestelling-detail: spiegelt de Filament ViewOrder-pagina zodat de
     * app dezelfde informatie toont. Bij een lijst-respons (geen `detail`-vlag)
     * blijft de payload compact.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status,
            'fulfillment_status' => $this->fulfillment_status,
            'is_packed' => (bool) $this->packed_at,
            'packed_at' => optional($this->packed_at)->toIso8601String(),
            'total' => $this->total !== null ? (float) $this->total : null,
            'customer_name' => trim((string) ($this->first_name . ' ' . $this->last_name)) ?: $this->email,
            'email' => $this->email,
            'order_origin' => $this->order_origin,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'products' => OrderProductResource::collection($this->whenLoaded('orderProducts')),
        ];

        // Compacte lijst-respons: alleen de basis.
        if (! $request->boolean('detail') && ! $this->resource->relationLoaded('orderPayments')) {
            return $base;
        }

        return array_merge($base, [
            'invoice_name' => $this->invoiceName,
            'hash' => $this->hash,
            'retour_status' => $this->retour_status,
            'credit_for_order_id' => $this->credit_for_order_id,
            'ip' => $this->ip,
            'marketing' => (bool) $this->marketing,
            'customer' => [
                'name' => trim((string) ($this->first_name . ' ' . $this->last_name)) ?: $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone_number' => $this->phone_number,
                'company_name' => $this->company_name,
                'btw_id' => $this->btw_id,
                'note' => $this->note,
            ],
            'shipping_address' => [
                'street' => $this->street,
                'house_nr' => $this->house_nr,
                'zip_code' => $this->zip_code,
                'city' => $this->city,
                'country' => $this->country,
            ],
            'invoice_address' => [
                'street' => $this->invoice_street ?: $this->street,
                'house_nr' => $this->invoice_house_nr ?: $this->house_nr,
                'zip_code' => $this->invoice_zip_code ?: $this->zip_code,
                'city' => $this->invoice_city ?: $this->city,
                'country' => $this->invoice_country ?: $this->country,
            ],
            'amounts' => [
                'subtotal' => $this->subtotal !== null ? (float) $this->subtotal : null,
                'btw' => $this->btw !== null ? (float) $this->btw : null,
                'discount' => $this->discount !== null ? (float) $this->discount : null,
                'total' => $this->total !== null ? (float) $this->total : null,
                'paid' => (float) $this->paidAmount,
                'open' => (float) $this->openAmount,
            ],
            'vat_percentages' => $this->vat_percentages,
            'vat_reverse_charge' => (bool) $this->vat_reverse_charge,
            'payment_method' => $this->paymentMethod,
            'payments' => $this->whenLoaded('orderPayments', fn () => $this->orderPayments->map(fn ($p) => [
                'id' => $p->id,
                'method' => $p->psp ?: ($p->payment_method ?? null),
                'psp' => $p->psp,
                'psp_id' => $p->psp_id ?? null,
                'amount' => $p->amount !== null ? (float) $p->amount : null,
                'status' => $p->status,
                'created_at' => optional($p->created_at)->toIso8601String(),
            ])->values(), []),
            'track_and_traces' => $this->whenLoaded('trackAndTraces', fn () => $this->trackAndTraces->map(fn ($tt) => [
                'id' => $tt->id,
                'supplier' => $tt->supplier ?? null,
                'delivery_company' => $tt->delivery_company ?? null,
                'code' => $tt->track_and_trace_code ?? $tt->code ?? null,
                'url' => $tt->track_and_trace_url ?? $tt->url ?? null,
                'status' => $tt->status ?? null,
                'expected_delivery_date' => optional($tt->expected_delivery_date)->toDateString(),
            ])->values(), []),
            'attribution' => array_filter([
                'utm_source' => $this->utm_source,
                'utm_medium' => $this->utm_medium,
                'utm_campaign' => $this->utm_campaign,
                'utm_term' => $this->utm_term,
                'utm_content' => $this->utm_content,
                'gclid' => $this->gclid,
                'fbclid' => $this->fbclid,
                'msclkid' => $this->msclkid,
                'landing_page' => $this->landing_page,
                'landing_page_referrer' => $this->landing_page_referrer,
            ], fn ($v) => ! empty($v)),
            'logs' => $this->logsPayload(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function logsPayload(): array
    {
        $logs = OrderLog::where('order_id', $this->id)->orderByDesc('created_at')->limit(50)->get();
        $userNames = User::whereIn('id', $logs->pluck('user_id')->filter()->unique())->pluck('name', 'id');

        return $logs->map(fn ($log) => [
            'id' => $log->id,
            'tag' => $log->tag,
            'note' => $log->note,
            'public_for_customer' => (bool) $log->public_for_customer,
            'user_name' => $log->user_id ? ($userNames[$log->user_id] ?? null) : null,
            'created_at' => optional($log->created_at)->toIso8601String(),
        ])->values()->all();
    }
}
