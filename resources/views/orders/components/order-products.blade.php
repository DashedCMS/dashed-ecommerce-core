<div>
    @php($lines = $this->lines())
    <x-dashed-core::aurora.card :title="__('Producten')" icon="heroicon-o-shopping-bag"
                                :badge="trans_choice(':count artikel|:count artikelen', count($lines), ['count' => count($lines)])"
                                badge-tone="neutral">
        <div class="au-od-products">
            <ul class="au-od-list">
                @foreach($lines as $line)
                    <li class="au-od-line @if($line['extras']) au-od-line--extras @endif">
                        @if($line['image'])
                            <img class="au-od-thumb" src="{{ $line['image'] }}" alt="" loading="lazy">
                        @endif
                        <div class="au-od-grow">
                            @if($line['url'])
                                <a class="au-od-strong" target="_blank" href="{{ $line['url'] }}">{{ $line['name'] }}</a>
                            @else
                                <span class="au-od-strong">{{ $line['name'] }}</span>
                            @endif
                            @if($line['badges'])
                                <div class="au-od-row au-od-gap-top">
                                    @foreach($line['badges'] as $badge)
                                        <x-dashed-core::aurora.badge :tone="\Dashed\DashedEcommerceCore\Classes\AuroraTone::from($badge['color'])">{{ $badge['label'] }}</x-dashed-core::aurora.badge>
                                    @endforeach
                                </div>
                            @endif
                            @if($line['extras'])
                                <div class="au-od-small au-od-extras au-od-gap-top">{{ __('Extra opties') }}: {!! $line['extras'] !!}</div>
                            @endif
                        </div>
                        <div class="au-od-qty au-od-muted">{{ $line['quantity'] }}{{ __('x') }}</div>
                        <div class="au-od-price">
                            <div class="au-od-strong">{{ $line['price'] }}</div>
                            @if($line['original'])
                                <div class="au-od-small au-od-muted au-od-strike">{{ $line['original'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <dl class="au-od-totals">
                @foreach($this->totals() as $row)
                    <div class="au-od-total-row {{ $row['total'] ? 'au-od-total-row--total' : '' }}">
                        <dt>
                            <span class="{{ $row['total'] ? '' : 'au-od-muted' }}">{{ $row['label'] }}</span>
                            @if($row['hint'])
                                <x-dashed-core::aurora.badge tone="neutral">{{ $row['hint'] }}</x-dashed-core::aurora.badge>
                            @endif
                        </dt>
                        <dd>{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </x-dashed-core::aurora.card>
</div>
