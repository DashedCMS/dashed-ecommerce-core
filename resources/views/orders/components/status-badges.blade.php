<div class="dvo-row">
    @foreach($this->badges() as $badge)
        @if($badge['url'])
            <a href="{{ $badge['url'] }}"><x-filament::badge :color="$badge['color']">{{ $badge['status'] }}</x-filament::badge></a>
        @else
            <x-filament::badge :color="$badge['color']">{{ $badge['status'] }}</x-filament::badge>
        @endif
    @endforeach
</div>
