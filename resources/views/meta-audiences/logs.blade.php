<div>
    <style>
        .dma-logs { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .dma-logs th, .dma-logs td { text-align: left; padding: .4rem .5rem; border-bottom: 1px solid rgba(127,127,127,.2); vertical-align: top; }
        .dma-logs .dma-failed { color: #b91c1c; }
    </style>
    @if ($logs->isEmpty())
        <p>{{ __('Nog geen synchronisaties.') }}</p>
    @else
        <table class="dma-logs">
            <thead>
            <tr>
                <th>{{ __('Tijdstip') }}</th>
                <th>{{ __('Soort') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Totaal') }}</th>
                <th>{{ __('Toegevoegd') }}</th>
                <th>{{ __('Verwijderd') }}</th>
                <th>{{ __('Melding') }}</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('d-m-Y H:i') }}</td>
                    <td>{{ \Dashed\DashedEcommerceCore\Models\MetaAudienceLog::typeLabel($log->type) }}</td>
                    <td class="{{ $log->status !== 'success' ? 'dma-failed' : '' }}">{{ \Dashed\DashedEcommerceCore\Models\MetaAudienceLog::statusLabel($log->status) }}</td>
                    <td>{{ $log->total ?? '-' }}</td>
                    <td>{{ $log->added }}</td>
                    <td>{{ $log->removed }}</td>
                    <td>{{ $log->message }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
