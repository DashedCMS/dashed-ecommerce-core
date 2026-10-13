<div>
    <x-filament::section>
        <x-slot name="heading">{{ __('Details') }}</x-slot>

        <dl class="dvo-dl">
            @foreach($this->details() as $label => $value)
                <dt class="dvo-muted">{{ $label }}</dt>
                <dd>{{ $value }}</dd>
            @endforeach
        </dl>
    </x-filament::section>
</div>
