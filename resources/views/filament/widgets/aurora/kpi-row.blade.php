@php($kpis = $this->getKpis())
<x-filament-widgets::widget>
    <div class="au-kpi-row">
        @foreach ($kpis as $kpi)
            <x-dashed-core::aurora.kpi
                :label="$kpi['label']"
                :value="$kpi['value']"
                :delta="$kpi['delta']"
                :delta-positive="$kpi['deltaPositive']"
                :sub="$kpi['sub']"
                :tone="$kpi['tone']"
                :icon="$kpi['icon']"
                :url="$kpi['url']"
            />
        @endforeach
    </div>
</x-filament-widgets::widget>
