<x-filament::page>
    <style>
        .custom-view-order .w-full .fi-btn {
            width: 100%;
        }
    </style>
    <div class="grid md:grid-cols-6 gap-4 custom-view-order">
        <div class="md:col-span-4">
            <div class="grid gap-4">
                <div>
                    @livewire('order-view-statusses', ['order' => $record])
                </div>
                <div>
                    @livewire('order-order-products-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-shipping-information-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-payment-information-list', ['order' => $record])
                </div>
                <div>
                    @livewire('order-payments-list', ['order' => $record])
                </div>
            </div>
        </div>
        <div class="md:col-span-2 flex flex-col gap-2">
            @livewire('order-customer-information-block-list', ['order' => $record])
            @if(!$record->credit_for_order_id)
                @livewire('change-order-fulfillment-status', ['order' => $record])
                @if($record->status == 'pending' || $record->status == 'partially_paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'cancelled')
                    @livewire('add-payment-to-order', ['order' => $record])
                @endif
                @livewire('cancel-order', ['order' => $record])
                @livewire('send-order-to-fulfillment-companies', ['order' => $record])
                @livewire('send-order-confirmation-to-email', ['order' => $record])
            @else
                @livewire('change-order-retour-status', ['order' => $record])
            @endif

            @foreach(ecommerce()->widgets('orders') as $widget)
                @if($widget['width'] == 'sidebar')
                    <livewire:is :component="$widget['name']" :order="$record"></livewire:is>
                @endif
            @endforeach
            @livewire('order-logs-list', ['order' => $record])
        </div>
    </div>
    <div class="max-w-[800px]">
        <p class="mb-4">Let op: de factuur en pakbon worden real-time gerenderd, en kunnen door bijv. verandering van
            betalingen verschillen van de daadwerkelijke factuur.</p>
        <iframe
            class="w-full h-[75vh] bg-white shadow-xl p-8"
            srcdoc="{{ view('dashed-ecommerce-core::invoices.invoice', ['order' => $this->record])->render() }}"
            frameborder="0"
        ></iframe>
    </div>
    <div class="max-w-[800px]">
        <iframe
            class="w-full h-[75vh] bg-white shadow-xl p-8"
            srcdoc="{{ view('dashed-ecommerce-core::invoices.packing-slip', ['order' => $this->record])->render() }}"
            frameborder="0"
        ></iframe>
    </div>
</x-filament::page>
