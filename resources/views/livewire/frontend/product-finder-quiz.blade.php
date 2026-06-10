<div>
    @if (! $this->finder)
        <p>De product finder is niet beschikbaar.</p>
    @elseif ($finished)
        @if (count($results))
            <div class="product-finder-results">
                <h3>Onze aanbevelingen</h3>
                @foreach ($results as $result)
                    <div class="product-finder-result" wire:key="pf-{{ $result['id'] }}">
                        <a href="{{ $result['url'] }}">{{ $result['name'] }}</a>
                        @if ($result['reason'])<p>{{ $result['reason'] }}</p>@endif
                        <button type="button" wire:click="addToCart({{ $result['id'] }})">In winkelwagen</button>
                    </div>
                @endforeach
                <button type="button" wire:click="addAll">Alles toevoegen</button>
            </div>
        @else
            <p>Geen passende producten gevonden.</p>
        @endif
        <button type="button" wire:click="restart">Opnieuw</button>
    @else
        @php($questions = $this->questions)
        @if (isset($questions[$step]))
            <div class="product-finder-question">
                <h3>{{ $questions[$step]['label'] }}</h3>
                @foreach (($questions[$step]['options'] ?? []) as $option)
                    <button type="button"
                        wire:click="selectAnswer(@js($questions[$step]['label']), @js($option['label']))">
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>
        @else
            <p>Deze finder heeft nog geen vragen.</p>
        @endif
    @endif
</div>
