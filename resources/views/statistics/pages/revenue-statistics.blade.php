<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}

        <div class="grid mt-6">
            <canvas wire:ignore x-data="{
                chart: null,

                init: function () {
                    chart = new Chart(
                        $el,
                        {
                            type: 'line',
                            data: @js($this->statistics['graph']),
                            options: null,
                        },
                    )

                    $wire.on('updatedStatistics', async ({ graph }) => {
                        chart.data = graph
                        chart.update('resize')
                    })
                },
            }" style="display: block; box-sizing: border-box; height: 200px;"></canvas>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4 col-span-full mt-6">
            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Aantal bestellingen
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['ordersAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Totaal bedrag
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['orderAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Gemiddelde waarde per order
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['averageOrderAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Aantal producten verkocht
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['productsSold'] }}
                    </div>

                </div>

            </div>
            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Betalingskosten
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['paymentCostsAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Verzendkosten
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['shippingCostsAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Korting
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['discountAmount'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        BTW
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['btwAmount'] }}
                    </div>

                </div>

            </div>
        </div>
    </form>

</x-filament::page>
