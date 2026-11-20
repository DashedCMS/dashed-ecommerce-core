@php
    $filters = ['all' => __('Alles'), 'status' => __('Statussen'), 'mail' => __('E-mails'), 'note' => __('Notities')];
@endphp
<div x-data="{ soort: 'all' }">
    <x-dashed-core::aurora.card :title="__('Activiteit & tijdlijn')" icon="heroicon-o-clock">
        <x-slot name="actions">
            <div class="au-od-tabs" role="group" aria-label="{{ __('Filter de tijdlijn') }}">
                @foreach($filters as $key => $label)
                    <button type="button" class="au-od-tab" x-on:click="soort = '{{ $key }}'" x-bind:class="{ 'au-od-tab--on': soort === '{{ $key }}' }" x-bind:aria-pressed="(soort === '{{ $key }}').toString()">{{ $label }}</button>
                @endforeach
            </div>
        </x-slot>

        <ul class="au-od-list au-od-timeline">
            @forelse($logs as $log)
                @php($type = $this->typeFor((string) $log->tag))
                <li class="au-od-line" wire:key="log-{{ $log->id }}" x-show="soort === 'all' || soort === '{{ $type }}'">
                    <span class="au-od-dot au-od-dot--{{ $type }}" aria-hidden="true"></span>
                    <div class="au-od-grow">
                        <div class="au-od-strong">{{ $log->tag() }}</div>
                        @if($log->note)
                            <div class="au-od-small au-od-muted">{!! nl2br(e($log->note)) !!}</div>
                        @endif
                        @if($log->images)
                            <div class="au-od-row au-od-gap-top">
                                @foreach($log->images as $image)
                                    @php($url = mediaHelper()->getSingleMedia($image, 'original')->url ?? '')
                                    <a target="_blank" href="{{ $url }}" download>
                                        @if(! \Illuminate\Support\Str::contains($url, '.pdf'))
                                            <img class="au-od-thumb" src="{{ $url }}" alt="">
                                        @else
                                            <span>{{ __('PDF Bestand') }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                        @if($log->public_for_customer)
                            <div class="au-od-small au-od-muted">{{ __('Notitie is zichtbaar voor de klant') }}</div>
                        @endif
                        @if($log->send_email_to_customer)
                            <div class="au-od-small au-od-muted">{{ __('Klant heeft een mail gehad van deze notitie') }}</div>
                        @endif
                        <div class="au-od-small au-od-muted">{{ $log->user_id ? ($log->user->name ?? __('Systeem')) : __('Systeem') }}</div>
                    </div>
                    <time class="au-od-when au-od-small au-od-muted" datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d-m-Y H:i') }}</time>
                </li>
            @empty
                <li class="au-od-line au-od-muted">{{ __('Nog geen activiteit bij deze bestelling.') }}</li>
            @endforelse
        </ul>
    </x-dashed-core::aurora.card>
</div>
