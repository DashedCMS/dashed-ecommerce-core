@php
    $filters = ['all' => __('Alles'), 'status' => __('Statussen'), 'mail' => __('E-mails'), 'note' => __('Notities')];
@endphp
<div x-data="{ soort: 'all' }">
    <x-filament::section>
        <x-slot name="heading">{{ __('Tijdlijn') }}</x-slot>
        <x-slot name="afterHeader">
            <div class="dvo-row">
                @foreach($filters as $key => $label)
                    <button type="button" class="dvo-filter" x-on:click="soort = '{{ $key }}'" x-bind:class="{ 'dvo-filter--on': soort === '{{ $key }}' }" x-bind:aria-pressed="soort === '{{ $key }}'">{{ $label }}</button>
                @endforeach
            </div>
        </x-slot>

        <div class="dvo-actions">
            <livewire:create-order-log :order="$order" color="gray" :key="'create-order-log-'.$order->id" />
        </div>

        <ul class="dvo-timeline">
            @foreach($logs as $log)
                @php($type = $this->typeFor((string) $log->tag))
                <li class="dvo-line" wire:key="log-{{ $log->id }}" x-show="soort === 'all' || soort === '{{ $type }}'">
                    <span class="dvo-dot dvo-dot--{{ $type }}" aria-hidden="true"></span>
                    <div class="dvo-when dvo-small dvo-muted">{{ $log->created_at->format('d-m-Y H:i') }}</div>
                    <div class="dvo-grow">
                        <div>{{ $log->tag() }}</div>
                        @if($log->note)
                            <div class="dvo-small dvo-muted">{!! nl2br(e($log->note)) !!}</div>
                        @endif
                        @if($log->images)
                            <div class="dvo-row dvo-gap-top">
                                @foreach($log->images as $image)
                                    @php($url = mediaHelper()->getSingleMedia($image, 'original')->url ?? '')
                                    <a target="_blank" href="{{ $url }}" download>
                                        @if(! \Illuminate\Support\Str::contains($url, '.pdf'))
                                            <img class="dvo-thumb" src="{{ $url }}" alt="">
                                        @else
                                            <span>{{ __('PDF Bestand') }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        @if($log->public_for_customer)
                            <div class="dvo-small dvo-muted">{{ __('Notitie is zichtbaar voor de klant') }}</div>
                        @endif
                        @if($log->send_email_to_customer)
                            <div class="dvo-small dvo-muted">{{ __('Klant heeft een mail gehad van deze notitie') }}</div>
                        @endif
                    </div>
                    <div class="dvo-small dvo-muted">{{ $log->user_id ? ($log->user->name ?? __('Systeem')) : __('Systeem') }}</div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</div>
