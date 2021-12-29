<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}

        <div class="grid mt-6">
            <canvas x-data="{
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

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3 col-span-full mt-6">
            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Aantal producten verkocht
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['totalQuantitySold'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Totaal bedrag
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['totalAmountSold'] }}
                    </div>

                </div>

            </div>

            <div class="relative p-6 rounded-2xl bg-white shadow">
                <div class="space-y-2">
                    <div class="text-sm font-medium text-gray-500">
                        Gemiddelde kosten per product
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['averageCostPerProduct'] }}
                    </div>

                </div>

            </div>
        </div>

        <div class="flex flex-col mt-6">
            <div class="shadow overflow-x-auto border-b border-gray-200 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Product
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Voorraad
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aantal verkocht
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Totaal opgeleverd
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($this->statistics['products'] as $product)
                        <tr class="bg-white">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $product->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $product->currentStock }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $product->quantitySold }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $product->amountSold }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

</x-filament::page>
