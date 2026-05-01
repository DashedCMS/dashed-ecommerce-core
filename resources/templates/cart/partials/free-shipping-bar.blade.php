@props(['progress' => ['gap' => 0, 'percentage' => 0, 'reached' => false], 'compact' => false])

@if (($progress['gap'] ?? 0) > 0 || ($progress['reached'] ?? false))
  <div class="rounded-md p-3 {{ $progress['reached'] ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200' }}">
    <div class="text-sm {{ $compact ? 'text-xs' : '' }} text-gray-700">
      @if ($progress['reached'])
        <strong class="text-green-700">{{ \Dashed\DashedTranslations\Models\Translation::get('free-shipping.reached', 'cart', 'Je hebt gratis verzending!') }}</strong>
      @else
        {!! str_replace(':amount:', '<strong class="text-green-700">€'.number_format($progress['gap'], 2, ',', '.').'</strong>', \Dashed\DashedTranslations\Models\Translation::get('free-shipping.under', 'cart', 'Nog :amount: voor gratis verzending')) !!}
      @endif
    </div>
    <div class="mt-2 h-2 rounded-full bg-gray-200 overflow-hidden">
      <div class="h-full bg-green-600 transition-all" style="width: {{ $progress['percentage'] }}%"></div>
    </div>
  </div>
@endif
