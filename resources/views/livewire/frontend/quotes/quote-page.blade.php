@php
    use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
    $exVat = (bool) $quote->prices_ex_vat;
@endphp

<section class="py-[clamp(40px,6vw,80px)]">
    <x-container>
        <div class="mx-auto max-w-3xl">
            <h1 class="font-display text-[clamp(26px,3vw,38px)] text-black">
                {{ Translation::get('quote', 'quote', 'Offerte') }} {{ $quote->displayNumber() }}
            </h1>

            @if ($quote->title)
                <p class="mt-2 text-lg">{{ $quote->title }}</p>
            @endif

            <p class="mt-1 text-sm opacity-70">
                {{ Translation::get('valid-until', 'quote', 'Geldig t/m') }}
                {{ $quote->valid_until?->format('d-m-Y') }}
            </p>

            @if ($quote->intro)
                <p class="mt-6">{!! nl2br(e($quote->intro)) !!}</p>
            @endif

            <div class="mt-8 divide-y">
                @foreach ($quote->lines as $line)
                    <div class="flex items-start gap-4 py-4">
                        <div class="pt-1">
                            @if ($line->is_optional && $line->choice_group)
                                <input type="radio"
                                       wire:click="chooseInGroup('{{ $line->choice_group }}', {{ $line->id }})"
                                       @checked($selected[$line->id] ?? false)>
                            @elseif ($line->is_optional)
                                <input type="checkbox"
                                       wire:click="toggleLine({{ $line->id }})"
                                       @checked($selected[$line->id] ?? false)>
                            @endif
                        </div>

                        <div class="flex-1">
                            <p class="font-medium">{{ $line->name }}</p>
                            @if ($line->description)
                                <p class="mt-1 text-sm opacity-70">{!! nl2br(e($line->description)) !!}</p>
                            @endif
                            @if ($line->is_optional)
                                <p class="mt-1 text-xs uppercase tracking-wide opacity-60">
                                    {{ $line->choice_group
                                        ? Translation::get('choice-option', 'quote', 'Keuze')
                                        : Translation::get('optional', 'quote', 'Optioneel') }}
                                </p>
                            @endif
                        </div>

                        <div class="w-20 text-right">{{ $line->quantity }}x</div>

                        <div class="w-32 text-right">
                            {{ CurrencyHelper::formatPrice($exVat ? $line->lineTotalExVat() : $line->lineTotal()) }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 text-right">
                <p>{{ Translation::get('subtotal-ex-vat', 'quote', 'Subtotaal ex btw') }}:
                    {{ CurrencyHelper::formatPrice($totals->totalExVat()) }}</p>
                @foreach ($totals->vatPerRate as $rate => $amount)
                    <p>{{ Translation::get('vat-percentage', 'quote', 'BTW :percentage:%', 'text', ['percentage' => $rate]) }}:
                        {{ CurrencyHelper::formatPrice($amount) }}</p>
                @endforeach
                <p class="text-lg font-semibold">
                    {{ Translation::get('total-incl-vat', 'quote', 'Totaal incl. btw') }}:
                    {{ CurrencyHelper::formatPrice($totals->total) }}
                </p>
            </div>

            @if ($quote->terms)
                <div class="mt-10">
                    <h2 class="text-xl">{{ Translation::get('terms', 'quote', 'Planning en voorwaarden') }}</h2>
                    <p class="mt-2">{!! nl2br(e($quote->terms)) !!}</p>
                </div>
            @endif

            @php($pdfUrl = \Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf::downloadUrl($quote))
            @if ($pdfUrl)
                <p class="mt-8">
                    <a href="{{ $pdfUrl }}" class="underline">
                        {{ Translation::get('download-pdf', 'quote', 'Offerte als PDF downloaden') }}
                    </a>
                </p>
            @endif
        </div>
    </x-container>
</section>
