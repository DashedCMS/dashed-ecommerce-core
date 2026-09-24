@foreach (\Dashed\DashedEcommerceCore\Services\Quotes\QuoteBranding::blocks($text) as $block)
    @if ($block['type'] === 'list')
        <ul class="{{ $listClass ?? '' }}">
            @foreach ($block['items'] as $item)
                <li>@if ($item['label'])<b>{{ $item['label'] }}:</b> @endif{{ $item['text'] }}</li>
            @endforeach
        </ul>
    @else
        <p class="{{ $paragraphClass ?? '' }}">{!! nl2br(e($block['text'])) !!}</p>
    @endif
@endforeach
