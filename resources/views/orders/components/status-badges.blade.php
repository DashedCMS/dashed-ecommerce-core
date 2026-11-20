<div class="au-od-row">
    @foreach($this->badges() as $badge)
        @php($tone = \Dashed\DashedEcommerceCore\Classes\AuroraTone::from($badge['color']))
        @if($badge['url'])
            <a href="{{ $badge['url'] }}"><x-dashed-core::aurora.badge :tone="$tone">{{ $badge['status'] }}</x-dashed-core::aurora.badge></a>
        @else
            <x-dashed-core::aurora.badge :tone="$tone">{{ $badge['status'] }}</x-dashed-core::aurora.badge>
        @endif
    @endforeach
</div>
