<x-filament::page>
    {{-- Eigen stijlblok: niet elk klantproject compileert de views uit vendor/dashed mee in Tailwind. --}}
    <style>
        .dvo { display: flex; flex-direction: column; gap: 1rem; font-size: .875rem; line-height: 1.25rem; }
        .dvo-grid { display: grid; gap: 1rem; align-items: start; }
        @media (min-width: 1024px) { .dvo-grid { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .dvo-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        .dvo-row { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .dvo-actions { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
        .dvo-actions > div { width: auto; }
        .dvo-muted { opacity: .65; }
        .dvo-small { font-size: .8125rem; }
        .dvo-strong { font-weight: 600; }
        .dvo-strike { text-decoration: line-through; }
        .dvo-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; }
        .dvo-gap-top { margin-top: .25rem; }
        .dvo-line { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .75rem 0; border-top: 1px solid rgba(128, 128, 128, .2); }
        .dvo-line:first-child { border-top: 0; padding-top: 0; }
        .dvo-line:last-child { padding-bottom: 0; }
        .dvo-grow { flex: 1 1 14rem; min-width: 0; overflow-wrap: anywhere; }
        .dvo-thumb { width: 3.5rem; height: 3.5rem; object-fit: cover; border-radius: .5rem; flex: none; }
        .dvo-qty { width: 3rem; text-align: right; }
        .dvo-price { min-width: 5.5rem; text-align: right; }
        .dvo-totals { display: flex; flex-direction: column; gap: .5rem; max-width: 24rem; margin: 1rem 0 0 auto; padding-top: 1rem; border-top: 1px solid rgba(128, 128, 128, .2); }
        .dvo-total-row { display: flex; justify-content: space-between; gap: 1rem; }
        .dvo-total-row--total { font-size: 1.125rem; font-weight: 700; padding-top: .5rem; border-top: 1px solid rgba(128, 128, 128, .2); }
        .dvo-dl { display: grid; grid-template-columns: minmax(0, 9rem) minmax(0, 1fr); gap: .5rem .75rem; margin: 0; }
        .dvo-dl dd { margin: 0; overflow-wrap: anywhere; }
        .dvo-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: .875rem 1.125rem; border-radius: .75rem; border: 1px solid #f59e0b; background: rgba(245, 158, 11, .1); }
        .dvo-steps { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(8rem, 1fr); gap: .5rem; overflow-x: auto; }
        .dvo-step { display: flex; flex-direction: column; gap: .375rem; }
        .dvo-step-bar { height: 4px; border-radius: 2px; background: rgba(128, 128, 128, .3); }
        .dvo-step--done .dvo-step-bar { background: #10b981; }
        .dvo-step--current .dvo-step-bar { background: #3b82f6; }
        .dvo-step--warning .dvo-step-bar { background: #f59e0b; }
        .dvo-step--danger .dvo-step-bar { background: #ef4444; }
        .dvo-step--todo .dvo-step-label { opacity: .65; }
        .dvo-timeline { list-style: none; margin: 1rem 0 0; padding: 0; }
        .dvo-timeline .dvo-line { align-items: baseline; }
        .dvo-dot { width: .5rem; height: .5rem; border-radius: 9999px; flex: none; background: #10b981; }
        .dvo-dot--mail { background: #3b82f6; }
        .dvo-dot--note { background: #a1a1aa; }
        .dvo-when { width: 7.5rem; flex: none; }
        .dvo-filter { padding: .25rem .625rem; border-radius: .5rem; font-size: .8125rem; font-weight: 600; opacity: .65; cursor: pointer; }
        .dvo-filter--on { opacity: 1; background: rgba(128, 128, 128, .18); }
        .dvo-docs { display: grid; gap: 1rem; }
        @media (min-width: 1024px) { .dvo-docs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .dvo-doc { width: 100%; height: 28rem; border: 0; border-radius: .5rem; background: #fff; }
        .dvo-address { font-style: normal; }
        .custom-view-order { display: flex; flex-direction: column; gap: .5rem; }
        .custom-view-order .w-full .fi-btn { width: 100%; }
    </style>

    @php
        $isCredit = (bool) $record->credit_for_order_id;
        $paymentGroup = $this->getPaymentActionGroup();
        $outstanding = $record->outstandingAmount();
        $sameInvoiceAddress = blank($record->invoice_street)
            || [$record->invoice_street, $record->invoice_house_nr, $record->invoice_zip_code, $record->invoice_city] == [$record->street, $record->house_nr, $record->zip_code, $record->city];
        $invoiceUrl = $record->downloadInvoiceUrl();
        $packingSlipUrl = $record->downloadPackingslipUrl();
    @endphp

    <div class="dvo">
        @livewire('order-view-statusses', ['order' => $record])

        @if($record->quote)
            <div class="dvo-row">
                <span class="dvo-muted">{{ __('Ontstaan uit offerte') }}</span>
                @can('view_quote')
                    <x-filament::link
                        :href="\Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource::getUrl('edit', ['record' => $record->quote])">
                        {{ $record->quote->displayNumber() }}
                    </x-filament::link>
                @else
                    <span class="dvo-strong">{{ $record->quote->displayNumber() }}</span>
                @endcan
            </div>
        @endif

        @foreach($openReturns as $return)
            <div class="dvo-banner">
                <div>
                    <div class="dvo-strong">{{ __('Retour #:id staat open: :status', ['id' => $return->id, 'status' => mb_strtolower($return->statusLabel())]) }}</div>
                    <div class="dvo-small dvo-muted">{{ __('Aangemeld op :datum', ['datum' => $return->created_at?->format('d-m-Y H:i')]) }}</div>
                </div>
                <x-filament::button tag="a" color="warning" icon="heroicon-o-arrow-uturn-left"
                                    :href="route('filament.dashed.resources.order-returns.view', [$return])">
                    {{ __('Retour behandelen') }}
                </x-filament::button>
            </div>
        @endforeach

        @if($steps)
            <x-filament::section>
                <div class="dvo-steps">
                    @foreach($steps as $step)
                        <div class="dvo-step dvo-step--{{ $step['state'] }}">
                            <div class="dvo-step-bar"></div>
                            <div class="dvo-strong dvo-step-label">{{ $step['label'] }}</div>
                            <div class="dvo-small dvo-muted">{{ $step['date']?->format('d-m-Y H:i') ?? ' ' }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <div class="dvo-grid">
            <div class="dvo-col">
                @livewire('order-order-products-list', ['order' => $record])

                <x-filament::section>
                    <x-slot name="heading">{{ __('Betaling') }}</x-slot>
                    <x-slot name="description">
                        @if($outstanding > 0 && ! $isCredit)
                            {{ __('Nog te betalen: :bedrag', ['bedrag' => \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($outstanding)]) }}
                        @else
                            {{ __($record->orderStatus()['status']) }}
                        @endif
                    </x-slot>
                    @if($paymentGroup)
                        <x-slot name="afterHeader">{{ $paymentGroup }}</x-slot>
                    @endif

                    @livewire('order-payments-list', ['order' => $record])
                </x-filament::section>

                @if(! $isCredit)
                    <x-filament::section>
                        <x-slot name="heading">{{ __('Verzending') }}</x-slot>
                        <x-slot name="description">{{ $record->fulfillmentStatus()['status'] }}</x-slot>
                        <x-slot name="afterHeader">
                            <div class="dvo-actions">
                                @livewire('change-order-fulfillment-status', ['order' => $record, 'color' => 'gray'])
                                @livewire('order-create-track-and-trace', ['order' => $record, 'color' => 'gray'])
                                @livewire('send-order-to-fulfillment-companies', ['order' => $record, 'color' => 'gray'])
                            </div>
                        </x-slot>

                        @livewire('order-shipping-information-list', ['order' => $record])
                    </x-filament::section>
                @else
                    <x-filament::section>
                        <x-slot name="heading">{{ __('Retour') }}</x-slot>
                        <x-slot name="description">{{ $record->fulfillmentStatus()['status'] }}</x-slot>
                        <x-slot name="afterHeader">
                            <div class="dvo-actions">
                                @livewire('change-order-retour-status', ['order' => $record, 'color' => 'gray'])
                            </div>
                        </x-slot>

                        @livewire('order-shipping-information-list', ['order' => $record])
                    </x-filament::section>
                @endif

                @include('dashed-ecommerce-core::orders.partials.order-returns', ['record' => $record])

                @livewire('order-logs-list', ['order' => $record])
            </div>

            <div class="dvo-col">
                @livewire('order-customer-information-block-list', ['order' => $record])

                <x-filament::section>
                    <x-slot name="heading">{{ __('Adressen') }}</x-slot>

                    <div class="dvo-col">
                        <div>
                            <div class="dvo-small dvo-muted">{{ __('Verzendadres') }}</div>
                            <address class="dvo-address">
                                @if($record->company_name){{ $record->company_name }}<br>@endif
                                {{ $record->name }}<br>
                                {{ $record->street }} {{ $record->house_nr }}<br>
                                {{ $record->zip_code }} {{ $record->city }}<br>
                                {{ $record->country }}
                            </address>
                        </div>
                        <div>
                            <div class="dvo-small dvo-muted">{{ __('Factuuradres') }}</div>
                            @if($sameInvoiceAddress)
                                <div>{{ __('Gelijk aan verzendadres') }}</div>
                            @else
                                <address class="dvo-address">
                                    @if($record->company_name){{ $record->company_name }}<br>@endif
                                    {{ $record->name }}<br>
                                    {{ $record->invoice_street }} {{ $record->invoice_house_nr }}<br>
                                    {{ $record->invoice_zip_code }} {{ $record->invoice_city }}<br>
                                    {{ $record->invoice_country }}
                                </address>
                            @endif
                        </div>
                    </div>
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">{{ __('Notitie van de klant') }}</x-slot>
                    <div class="{{ $record->note ? '' : 'dvo-muted' }}">{!! $record->note ? nl2br(e($record->note)) : e(__('Geen notitie')) !!}</div>
                </x-filament::section>

                @livewire('order-payment-information-list', ['order' => $record])

                @if(! $isCredit)
                    <x-filament::section>
                        <x-slot name="heading">{{ __('Overige acties') }}</x-slot>
                        <div class="dvo-actions">
                            @livewire('send-order-confirmation-to-email', ['order' => $record, 'color' => 'gray'])
                            @livewire('cancel-order', ['order' => $record, 'color' => 'danger'])
                        </div>
                    </x-filament::section>
                @endif

                @if($sidebarWidgets)
                    <x-filament::section>
                        <x-slot name="heading">{{ __('Koppelingen') }}</x-slot>
                        <div class="custom-view-order">
                            @foreach($sidebarWidgets as $widget)
                                <livewire:is :component="$widget['name']" :order="$record" :key="'order-widget-'.$widget['name']"></livewire:is>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                @livewire('order-abandonment-diagnosis-list', ['order' => $record], key('order-abandonment-'.$record->id))
                @livewire('order-attribution-information-list', ['order' => $record], key('order-attribution-'.$record->id))
            </div>
        </div>

        <x-filament::section collapsible>
            <x-slot name="heading">{{ __('Documenten') }}</x-slot>
            <x-slot name="description">{{ __('De factuur en pakbon worden live opgebouwd en kunnen door bijvoorbeeld een gewijzigde betaling afwijken van de verstuurde factuur.') }}</x-slot>

            <div class="dvo-docs">
                <div class="dvo-col">
                    <div class="dvo-row">
                        <span class="dvo-strong">{{ __('Factuur') }}</span>
                        @if($invoiceUrl)
                            <x-filament::link :href="$invoiceUrl" target="_blank" icon="heroicon-s-arrow-down-tray">{{ __('Downloaden') }}</x-filament::link>
                        @endif
                    </div>
                    <iframe class="dvo-doc" title="{{ __('Factuur') }}" loading="lazy"
                            srcdoc="{{ view('dashed-ecommerce-core::invoices.invoice', ['order' => $record])->render() }}"></iframe>
                </div>
                <div class="dvo-col">
                    <div class="dvo-row">
                        <span class="dvo-strong">{{ __('Pakbon') }}</span>
                        @if($packingSlipUrl)
                            <x-filament::link :href="$packingSlipUrl" target="_blank" icon="heroicon-s-arrow-down-tray">{{ __('Downloaden') }}</x-filament::link>
                        @endif
                    </div>
                    <iframe class="dvo-doc" title="{{ __('Pakbon') }}" loading="lazy"
                            srcdoc="{{ view('dashed-ecommerce-core::invoices.packing-slip', ['order' => $record])->render() }}"></iframe>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament::page>
