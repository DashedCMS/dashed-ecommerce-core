<div>
    <x-dashed-core::aurora.card :title="__('Details')" icon="heroicon-o-information-circle">
        @foreach($this->details() as $label => $value)
            <x-dashed-core::aurora.info-row :label="$label" :value="$value" />
        @endforeach
    </x-dashed-core::aurora.card>
</div>
