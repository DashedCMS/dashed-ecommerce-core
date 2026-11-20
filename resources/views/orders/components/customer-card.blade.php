@php
    $record = $order;
    $sameInvoiceAddress = blank($record->invoice_street)
        || [$record->invoice_street, $record->invoice_house_nr, $record->invoice_zip_code, $record->invoice_city] == [$record->street, $record->house_nr, $record->zip_code, $record->city];
    $profileUrl = ($record->user_id || $record->email) && \Dashed\DashedEcommerceCore\Filament\Pages\CustomerProfilePage::canAccess()
        ? \Dashed\DashedEcommerceCore\Filament\Pages\CustomerProfilePage::urlForOrder($record)
        : null;
    $historyText = $this->historyText();
@endphp
<div>
    <x-dashed-core::aurora.card :title="__('Klantgegevens')" icon="heroicon-o-user">
        @if($profileUrl)
            <x-slot name="actions">
                <a class="au-od-link au-od-small" href="{{ $profileUrl }}">{{ __('Bekijk profiel') }} &rarr;</a>
            </x-slot>
        @endif

        <div class="au-od-customer">
            @if($picture = $this->profilePicture())
                <img class="au-od-avatar" src="{{ $picture }}" alt="" loading="lazy">
            @endif
            <div class="au-od-grow">
                <div class="au-od-strong">{{ $record->company_name ? $record->company_name.' · ' : '' }}{{ $record->name }}</div>
                @if($historyText)
                    <div class="au-od-small au-od-muted">{{ $historyText }}</div>
                @endif
                {{ $this->customerHistoryAction }}
            </div>
        </div>

        <div class="au-od-contact">
            @if($record->email)
                <div class="au-od-contact-item">
                    <x-filament::icon icon="heroicon-o-envelope" />
                    <a class="au-od-link" href="mailto:{{ $record->email }}">{{ $record->email }}</a>
                </div>
            @endif
            @if($record->phone_number)
                <div class="au-od-contact-item">
                    <x-filament::icon icon="heroicon-o-phone" />
                    <a class="au-od-link" href="tel:{{ $record->phone_number }}">{{ $record->phone_number }}</a>
                </div>
            @endif
            <div class="au-od-contact-item">
                <x-filament::icon icon="heroicon-o-map-pin" />
                <div>
                    <div class="au-od-label au-od-muted">{{ __('Verzendadres') }}</div>
                    <address class="au-od-address">
                        {{ $record->street }} {{ $record->house_nr }}<br>
                        {{ $record->zip_code }} {{ $record->city }}<br>
                        {{ $record->country }}
                    </address>
                </div>
            </div>
            <div class="au-od-contact-item">
                <x-filament::icon icon="heroicon-o-document-text" />
                <div>
                    <div class="au-od-label au-od-muted">{{ __('Factuuradres') }}</div>
                    @if($sameInvoiceAddress)
                        <div>{{ __('Gelijk aan verzendadres') }}</div>
                    @else
                        <address class="au-od-address">
                            {{ $record->invoice_street }} {{ $record->invoice_house_nr }}<br>
                            {{ $record->invoice_zip_code }} {{ $record->invoice_city }}<br>
                            {{ $record->invoice_country }}
                        </address>
                    @endif
                </div>
            </div>
        </div>
    </x-dashed-core::aurora.card>

    <x-filament-actions::modals />
</div>
