<div>
    @if($allowed && $logs->isNotEmpty())
        <x-dashed-core::aurora.card :title="__('Carrier-logboek')" icon="heroicon-o-truck">
            <div style="display: flex; flex-direction: column; gap: .375rem;">
                @foreach($logs as $log)
                    <a href="{{ \Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource::getUrl('view', ['record' => $log]) }}"
                       style="display: grid; grid-template-columns: 8.5rem 5.5rem 1fr auto auto; gap: .75rem; align-items: center; padding: .375rem .5rem; border-radius: .5rem; text-decoration: none; color: inherit; font-size: .875rem;">
                        <span style="opacity: .7;">{{ $log->created_at?->format('d-m-Y H:i:s') }}</span>
                        <span>{{ $log->carrier }}</span>
                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $log->action }}</span>
                        <span style="padding: .125rem .5rem; border-radius: 999px; font-weight: 600; font-size: .75rem; {{ $log->failed() ? 'background: rgba(239, 68, 68, .15); color: #dc2626;' : 'background: rgba(34, 197, 94, .15); color: #16a34a;' }}">
                            {{ \Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource::statusLabel($log->response_status) }}
                        </span>
                        <span style="opacity: .7; text-align: right;">{{ $log->duration_ms !== null ? $log->duration_ms . ' ms' : '-' }}</span>
                    </a>
                @endforeach
                @if($total > $logs->count())
                    <a href="{{ $indexUrl }}" style="font-size: .875rem; opacity: .8;">{{ __('Alle :aantal requests bekijken', ['aantal' => $total]) }}</a>
                @endif
            </div>
        </x-dashed-core::aurora.card>
    @endif
</div>
