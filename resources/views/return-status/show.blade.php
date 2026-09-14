@php use Dashed\DashedTranslations\Models\Translation; @endphp
@php($order = $orderReturn->order)
<div class="max-w-2xl mx-auto py-10 px-4">
    <h1 class="text-2xl font-bold">{{ Translation::get('return-status-title', 'returns', 'Status van je retour') }}</h1>

    <div class="mt-4 rounded border p-4">
        <p class="font-medium">{{ Translation::get('return-status-order', 'returns', 'Bestelling') }}:
            {{ $order?->invoice_id ?: ('#' . $orderReturn->order_id) }}</p>
        <p class="mt-1">{{ Translation::get('return-status-current', 'returns', 'Status') }}:
            <strong>{{ \Dashed\DashedEcommerceCore\Models\OrderReturn::statusLabels()[$orderReturn->status] ?? $orderReturn->status }}</strong>
        </p>
    </div>

    <h2 class="mt-6 text-lg font-semibold">{{ Translation::get('return-status-timeline', 'returns', 'Tijdlijn') }}</h2>
    <ul class="mt-2 space-y-1 text-sm text-gray-700">
        @if ($orderReturn->requested_at)
            <li>{{ Translation::get('return-status-requested', 'returns', 'Aangevraagd') }}: {{ $orderReturn->requested_at->format('d-m-Y H:i') }}</li>
        @endif
        @if ($orderReturn->approved_at)
            <li>{{ Translation::get('return-status-approved', 'returns', 'Goedgekeurd') }}: {{ $orderReturn->approved_at->format('d-m-Y H:i') }}</li>
        @endif
        @if ($orderReturn->rejected_at)
            <li>{{ Translation::get('return-status-rejected', 'returns', 'Afgekeurd') }}: {{ $orderReturn->rejected_at->format('d-m-Y H:i') }}</li>
        @endif
        @if ($orderReturn->status === \Dashed\DashedEcommerceCore\Models\OrderReturn::STATUS_HANDLED && ($orderReturn->processed_at ?? $orderReturn->handled_at))
            <li>{{ Translation::get('return-status-processed', 'returns', 'Verwerkt') }}: {{ ($orderReturn->processed_at ?? $orderReturn->handled_at)->format('d-m-Y H:i') }}
                @if ($orderReturn->creditOrder)
                    ({{ Translation::get('return-status-credited', 'returns', 'gecrediteerd') }}: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($orderReturn->creditedAmount()) }})
                @endif
            </li>
        @endif
        @php($refund = $orderReturn->refundPayment())
        @if ($refund)
            <li>{{ Translation::get('return-status-refunded', 'returns', 'Terugbetaald') }}: {{ $refund->created_at?->format('d-m-Y H:i') }},
                {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice(abs((float) $refund->amount)) }}
                @if ($refund->payment_method) ({{ $refund->payment_method }}) @endif
            </li>
        @endif
        @if ($orderReturn->closed_at)
            <li>{{ Translation::get('return-status-closed', 'returns', 'Gesloten') }}: {{ $orderReturn->closed_at->format('d-m-Y H:i') }}</li>
        @endif
    </ul>

    @if ($orderReturn->status === \Dashed\DashedEcommerceCore\Models\OrderReturn::STATUS_REJECTED && $orderReturn->rejected_reason)
        <div class="mt-4 rounded border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <strong>{{ Translation::get('return-status-reason', 'returns', 'Reden') }}:</strong> {{ $orderReturn->rejected_reason }}
        </div>
    @endif

    @if ($orderReturn->status === \Dashed\DashedEcommerceCore\Models\OrderReturn::STATUS_CLOSED && $orderReturn->closed_reason)
        <div class="mt-4 rounded border border-gray-200 bg-gray-50 p-4 text-sm text-gray-800">
            <strong>{{ Translation::get('return-status-reason', 'returns', 'Reden') }}:</strong> {{ $orderReturn->closed_reason }}
        </div>
    @endif

    <h2 class="mt-6 text-lg font-semibold">{{ Translation::get('return-status-products', 'returns', 'Geretourneerde producten') }}</h2>
    <ul class="mt-2 space-y-2">
        @foreach ($orderReturn->lines as $line)
            <li class="flex items-center gap-3">
                @php($img = $line->orderProduct?->product?->firstImage ?? null)
                @if ($img)
                    <img src="{{ $img }}" alt="{{ $line->orderProduct?->name }}" class="w-12 h-12 object-cover rounded" />
                @endif
                <span>{{ $line->quantity }}x {{ $line->orderProduct?->name }}</span>
                @if ($orderReturn->status === \Dashed\DashedEcommerceCore\Models\OrderReturn::STATUS_HANDLED)
                    <span class="text-gray-500">({{ Translation::get('return-status-credited-qty', 'returns', 'gecrediteerd') }}: {{ (int) $line->processed_quantity }})</span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($orderReturn->return_label_path)
        <a href="{{ route('dashed.frontend.return-status.label', $orderReturn->hash) }}"
           class="button button--primary mt-6 inline-block">
            {{ Translation::get('return-status-download-label', 'returns', 'Retourlabel downloaden') }}
        </a>
    @endif

    @php($creditInvoiceUrl = $orderReturn->creditOrder ? rescue(fn () => $orderReturn->creditOrder->downloadInvoiceUrl(), null, false) : null)
    @if ($creditInvoiceUrl)
        <a href="{{ $creditInvoiceUrl }}" class="button button--primary mt-6 inline-block">
            {{ Translation::get('return-status-download-credit-invoice', 'returns', 'Creditfactuur downloaden') }}
        </a>
    @endif

    @livewire('order-return-thread', ['hash' => $orderReturn->hash])
</div>
