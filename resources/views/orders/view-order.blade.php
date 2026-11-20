<x-filament::page>
    @include('dashed-ecommerce-core::orders.partials.aurora-styles')

    @php
        $isCredit = (bool) $record->credit_for_order_id;
        $paymentGroup = $this->getPaymentActionGroup();
        $outstanding = $record->outstandingAmount();
        $orderStatus = $record->orderStatus();
        $fulfillmentStatus = $record->fulfillmentStatus();
        $invoiceUrl = $record->downloadInvoiceUrl();
        $packingSlipUrl = $record->downloadPackingslipUrl();
        $parcelType = \Dashed\DashedEcommerceCore\Classes\ParcelType::forOrder($record);
        $tone = fn (?string $color): string => \Dashed\DashedEcommerceCore\Classes\AuroraTone::from($color);
        // De eerstvolgende stap is verzenden zodra er betaald is en de bestelling
        // nog niet is afgehandeld; dan is de statusknop de primaire actie.
        $fulfillmentIsNext = ! $isCredit && $record->isPaidFor() && $record->fulfillment_status !== 'handled';
        $stepperSteps = array_map(fn (array $step): array => [
            'label' => $step['label'],
            'sub' => $step['date']?->format('d-m-Y H:i'),
            'state' => $step['state'],
        ], $steps);
    @endphp

    <div class="au-od">
        {{-- Kopbadges: betaal- en verzendstatus altijd in beeld. --}}
        <div class="au-od-head">
            @livewire('order-view-statusses', ['order' => $record])
            @unless($isCredit)
                <span class="au-od-parcel" data-parcel-type="{{ $parcelType['key'] }}"
                      style="background: {{ \Dashed\DashedEcommerceCore\Classes\ParcelType::hex($parcelType['key']) }};"
                      title="{{ __('Verzendvorm') }}">
                    <x-filament::icon :icon="\Dashed\DashedEcommerceCore\Classes\ParcelType::icon($parcelType['key'])" />
                    <span><span class="sr-only">{{ __('Verzendvorm') }}: </span>{{ $parcelType['label'] }}</span>
                </span>
            @endunless
        </div>

        @livewire('order-add-on-banner', ['order' => $record], key('order-add-on-banner-'.$record->id))
        @livewire('order-label-problem-banner', ['order' => $record], key('order-label-problem-'.$record->id))

        @if($record->quote)
            <div class="au-od-row">
                <span class="au-od-muted">{{ __('Ontstaan uit offerte') }}</span>
                @can('view_quote')
                    <a class="au-od-link" href="{{ \Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource::getUrl('edit', ['record' => $record->quote]) }}">{{ $record->quote->displayNumber() }}</a>
                @else
                    <span class="au-od-strong">{{ $record->quote->displayNumber() }}</span>
                @endcan
            </div>
        @endif

        @foreach($openReturns as $return)
            <div class="au-od-banner">
                <div>
                    <div class="au-od-strong">{{ __('Retour #:id staat open: :status', ['id' => $return->id, 'status' => mb_strtolower($return->statusLabel())]) }}</div>
                    <div class="au-od-small au-od-muted">{{ __('Aangemeld op :datum', ['datum' => $return->created_at?->format('d-m-Y H:i')]) }}</div>
                </div>
                <x-filament::button tag="a" color="warning" icon="heroicon-o-arrow-uturn-left"
                                    :href="route('filament.dashed.resources.order-returns.view', [$return])">
                    {{ __('Retour behandelen') }}
                </x-filament::button>
            </div>
        @endforeach

        @if($stepperSteps)
            <x-dashed-core::aurora.stepper :steps="$stepperSteps" />
        @endif

        <div class="au-od-grid">
            <div class="au-od-col">
                @livewire('order-order-products-list', ['order' => $record])

                <div class="au-od-pair">
                    <x-dashed-core::aurora.card :title="__('Betaling')" icon="heroicon-o-credit-card"
                                                :badge="__($orderStatus['status'])" :badge-tone="$tone($orderStatus['color'])">
                        @if($paymentGroup)
                            <x-slot name="actions">{{ $paymentGroup }}</x-slot>
                        @endif

                        @if($outstanding > 0 && ! $isCredit)
                            <x-dashed-core::aurora.info-row :label="__('Nog te betalen')"
                                                            :value="\Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($outstanding)" />
                        @endif

                        @livewire('order-payments-list', ['order' => $record])
                    </x-dashed-core::aurora.card>

                    @if(! $isCredit)
                        <x-dashed-core::aurora.card :title="__('Verzending')" icon="heroicon-o-truck"
                                                    :badge="$fulfillmentStatus['status']" :badge-tone="$tone($fulfillmentStatus['color'])">
                            @livewire('order-shipping-information-list', ['order' => $record])
                        </x-dashed-core::aurora.card>
                    @else
                        <x-dashed-core::aurora.card :title="__('Retour')" icon="heroicon-o-arrow-uturn-left"
                                                    :badge="$fulfillmentStatus['status']" :badge-tone="$tone($fulfillmentStatus['color'])">
                            <x-slot name="actions">
                                @livewire('change-order-retour-status', ['order' => $record, 'color' => 'gray'])
                            </x-slot>

                            @livewire('order-shipping-information-list', ['order' => $record])
                        </x-dashed-core::aurora.card>
                    @endif
                </div>

                @include('dashed-ecommerce-core::orders.partials.order-returns', ['record' => $record])

                @livewire('order-logs-list', ['order' => $record])
            </div>

            <div class="au-od-col">
                @livewire('order-customer-information-block-list', ['order' => $record])

                @if(filled($record->note))
                    <x-dashed-core::aurora.card :title="__('Notitie van de klant')" icon="heroicon-o-chat-bubble-left-ellipsis">
                        <div>{!! nl2br(e($record->note)) !!}</div>
                    </x-dashed-core::aurora.card>
                @endif

                @if(! $isCredit)
                    <div class="au-od-first-on-mobile">
                        <x-dashed-core::aurora.card :title="__('Snelle acties')" icon="heroicon-o-bolt">
                            <div class="au-od-quick">
                                @livewire('change-order-fulfillment-status', ['order' => $record, 'color' => $fulfillmentIsNext ? 'primary' : 'gray'])
                                @livewire('order-create-track-and-trace', ['order' => $record, 'color' => 'gray'])
                                @livewire('send-order-to-fulfillment-companies', ['order' => $record, 'color' => 'gray'])
                                @livewire('create-order-log', ['order' => $record, 'color' => 'gray'], key('create-order-log-'.$record->id))
                                @livewire('send-order-confirmation-to-email', ['order' => $record, 'color' => 'gray'])
                                @livewire('cancel-order', ['order' => $record, 'color' => 'danger'])
                            </div>
                        </x-dashed-core::aurora.card>
                    </div>
                @else
                    <div class="au-od-first-on-mobile">
                        <x-dashed-core::aurora.card :title="__('Snelle acties')" icon="heroicon-o-bolt">
                            <div class="au-od-quick">
                                @livewire('create-order-log', ['order' => $record, 'color' => 'gray'], key('create-order-log-'.$record->id))
                            </div>
                        </x-dashed-core::aurora.card>
                    </div>
                @endif

                <x-dashed-core::aurora.card :title="__('Documenten')" icon="heroicon-o-document-text">
                    <ul class="au-od-list">
                        <li class="au-od-line au-od-doc">
                            <span class="au-od-strong">{{ $isCredit ? __('Creditfactuur :nummer', ['nummer' => $record->invoice_id]) : __('Factuur :nummer', ['nummer' => $record->invoice_id]) }}</span>
                            @if($invoiceUrl)
                                <a class="au-od-link" href="{{ $invoiceUrl }}" target="_blank" rel="noopener">{{ __('Downloaden') }} &rarr;</a>
                            @else
                                <span class="au-od-small au-od-muted">{{ __('Nog niet beschikbaar') }}</span>
                            @endif
                        </li>
                        <li class="au-od-line au-od-doc">
                            <span class="au-od-strong">{{ __('Pakbon') }}</span>
                            @if($packingSlipUrl)
                                <a class="au-od-link" href="{{ $packingSlipUrl }}" target="_blank" rel="noopener">{{ __('Downloaden') }} &rarr;</a>
                            @else
                                <span class="au-od-small au-od-muted">{{ __('Nog niet beschikbaar') }}</span>
                            @endif
                        </li>
                    </ul>
                </x-dashed-core::aurora.card>

                @livewire('order-payment-information-list', ['order' => $record])

                @if($sidebarWidgets)
                    <x-dashed-core::aurora.card :title="__('Koppelingen')" icon="heroicon-o-link">
                        <div class="au-od-quick">
                            @foreach($sidebarWidgets as $widget)
                                <livewire:is :component="$widget['name']" :order="$record" :key="'order-widget-'.$widget['name']"></livewire:is>
                            @endforeach
                        </div>
                    </x-dashed-core::aurora.card>
                @endif

                @livewire('order-abandonment-diagnosis-list', ['order' => $record], key('order-abandonment-'.$record->id))
                @livewire('order-attribution-information-list', ['order' => $record], key('order-attribution-'.$record->id))
            </div>
        </div>

        <x-dashed-core::aurora.card :title="__('Voorbeeld van de documenten')" icon="heroicon-o-eye">
            <details>
                <summary class="au-od-summary">{{ __('Factuur en pakbon tonen') }}</summary>
                <p class="au-od-small au-od-muted au-od-gap-top">{{ __('De factuur en pakbon worden live opgebouwd en kunnen door bijvoorbeeld een gewijzigde betaling afwijken van de verstuurde factuur.') }}</p>
                <div class="au-od-preview">
                    <iframe title="{{ __('Factuur') }}" loading="lazy"
                            srcdoc="{{ view('dashed-ecommerce-core::invoices.invoice', ['order' => $record])->render() }}"></iframe>
                    <iframe title="{{ __('Pakbon') }}" loading="lazy"
                            srcdoc="{{ view('dashed-ecommerce-core::invoices.packing-slip', ['order' => $record])->render() }}"></iframe>
                </div>
            </details>
        </x-dashed-core::aurora.card>
    </div>
</x-filament::page>
