<div>
    <x-filament::section>
        <x-slot name="heading">{{ __('Producten') }}</x-slot>

        <div>
            @foreach($this->lines() as $line)
                <div class="dvo-line">
                    @if($line['image'])
                        <img class="dvo-thumb" src="{{ $line['image'] }}" alt="" loading="lazy">
                    @endif
                    <div class="dvo-grow">
                        @if($line['url'])
                            <a class="dvo-strong" target="_blank" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
                        @else
                            <span class="dvo-strong">{{ $line['name'] }}</span>
                        @endif
                        @if($line['badges'])
                            <div class="dvo-row dvo-gap-top">
                                @foreach($line['badges'] as $badge)
                                    <x-filament::badge :color="$badge['color']">{{ $badge['label'] }}</x-filament::badge>
                                @endforeach
                            </div>
                        @endif
                        @if($line['extras'])
                            <div class="dvo-small dvo-muted dvo-gap-top">{{ $line['extras'] }}</div>
                        @endif
                    </div>
                    <div class="dvo-qty">{{ $line['quantity'] }}{{ __('x') }}</div>
                    <div class="dvo-price">
                        <div class="dvo-strong">{{ $line['price'] }}</div>
                        @if($line['original'])
                            <div class="dvo-small dvo-muted dvo-strike">{{ $line['original'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="dvo-totals">
            @foreach($this->totals() as $row)
                <div class="dvo-total-row {{ $row['total'] ? 'dvo-total-row--total' : '' }}">
                    <span>
                        <span class="{{ $row['total'] ? '' : 'dvo-muted' }}">{{ $row['label'] }}</span>
                        @if($row['hint'])
                            <x-filament::badge color="gray">{{ $row['hint'] }}</x-filament::badge>
                        @endif
                    </span>
                    <span>{{ $row['value'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</div>
