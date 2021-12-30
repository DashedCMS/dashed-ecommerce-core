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
                        Gemiddelde korting per order
                    </div>

                    <div class="text-3xl">
                        {{ $this->statistics['data']['averageDiscountAmount'] }}
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
        </div>

        <div class="flex flex-col mt-6">
            <div class="shadow overflow-x-auto border-b border-gray-200 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Bestelling ID
                        </th>
                        @if(count(Sites::getSites()) > 1)
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Site
                            </th>
                        @endif
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Betaalmethode
                        </th>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Klant
                        </th>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Korting
                        </th>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Totaal
                        </th>
                        <th scope="col"
                            class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Aangemaakt op
                        </th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($this->statistics['orders'] as $order)
                        <tr v-for="order in orders">
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ $order->invoice_id }}
                            </td>
                            @if(count(Sites::getSites()) > 1)
                                <td class="px-4 py-2 whitespace-nowrap text-xs text-gray-500 space-x-2">
                                    <span
                                        class="bg-green-100 text-green-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                    {{ $order->site_id }}
                                    </span>
                                </td>
                            @endif
                            <th scope="col"
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ $order->paymentMethod }}
                            </th>
                            <td class="px-4 py-2 text-xs text-gray-500 flex-1 space-y-1">
                                @if($order->status == 'pending')
                                    <span
                                        class="bg-blue-100 text-blue-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                                Lopende aankoop
                                                </span>
                                @elseif($order->status == 'cancelled')
                                    <span
                                        class="bg-red-100 text-red-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                                Geannuleerd
                                                </span>
                                @elseif($order->status == 'waiting_for_confirmation')
                                    <span
                                        class="bg-purple-100 text-purple-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                                Wachten op bevestiging betaling
                                                </span>
                                @elseif($order->status == 'return')
                                    <span
                                        class="bg-yellow-100 text-yellow-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                                Retour
                                                </span>
                                @else
                                    <span
                                        class="bg-green-100 text-green-800 inline-flex items-center px-3 py-0.5 rounded-full text-xs font-medium">
                                                Betaald
                                                </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ $order->name }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ CurrencyHelper::formatPrice($order->discount) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ CurrencyHelper::formatPrice($order->total) }}
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs font-medium text-gray-900">
                                {{ $order->created_at }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

</x-filament::page>
