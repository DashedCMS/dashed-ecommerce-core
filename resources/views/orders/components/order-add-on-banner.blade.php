<div>
    @php
        $boxStyle = 'padding: 1rem 1.25rem; border-radius: .75rem; border: 2px solid #f59e0b; background: rgba(245, 158, 11, .14); display: flex; flex-direction: column; gap: .625rem;';
        $titleStyle = 'font-size: 1.125rem; line-height: 1.5rem; font-weight: 700;';
    @endphp

    @if($link && $link->isLinked() && ! $link->paid_at)
        <div class="dvo-add-on-banner" data-add-on="child-unpaid" style="{{ $boxStyle }}">
            <div style="{{ $titleStyle }}">
                {{ __('Bijbestelling bij') }} #{{ $parent ? \Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display::number($parent) : $link->parent_order_id }}:
                {{ __('nog niet betaald, gaat pas mee in de doos na betaling') }}
            </div>
        </div>
    @elseif($link && $link->isLinked())
        <div class="dvo-add-on-banner" data-add-on="child" style="{{ $boxStyle }}">
            <div style="{{ $titleStyle }}">
                {{ __('Bijbestelling bij') }}
                @if($parent)
                    <x-filament::link :href="\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('view', ['record' => $parent])">#{{ \Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display::number($parent) }}</x-filament::link>:
                @else
                    #{{ $link->parent_order_id }}:
                @endif
                {{ __('wordt meegestuurd in dezelfde doos, geen eigen verzendlabel') }}
            </div>
        </div>
    @elseif($link)
        <div class="dvo-add-on-banner" data-add-on="separate" style="{{ $boxStyle }}">
            <div style="{{ $titleStyle }}">
                {{ $link->reason ? __('Losse zending (:reden)', ['reden' => $link->reason]) : __('Losse zending') }}
            </div>
            <div class="au-od-small">
                {{ __('Bijbestelling die eerst bij') }}
                @if($parent)
                    <x-filament::link :href="\Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::getUrl('view', ['record' => $parent])">#{{ \Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display::number($parent) }}</x-filament::link>
                @else
                    #{{ $link->parent_order_id }}
                @endif
                {{ __('hoorde. Deze order krijgt een eigen verzendlabel.') }}
            </div>
        </div>
    @endif

    @if($addOns->isNotEmpty())
        <div class="dvo-add-on-banner" data-add-on="parent" style="{{ $boxStyle }}">
            @foreach($addOns as $addOn)
                <div style="{{ $titleStyle }}">
                    {{ __('Let op: verzenden mét bijbestelling') }}
                    <x-filament::link :href="$addOn['url']">#{{ $addOn['number'] }}</x-filament::link>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left;">
                            <th style="padding: .25rem .5rem .25rem 0; width: 4rem;">{{ __('Aantal') }}</th>
                            <th style="padding: .25rem .5rem;">{{ __('Product') }}</th>
                            <th style="padding: .25rem 0 .25rem .5rem;">{{ __('Opties / SKU') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($addOn['lines'] as $line)
                            <tr style="border-top: 1px solid rgba(245, 158, 11, .4);">
                                <td style="padding: .25rem .5rem .25rem 0; font-weight: 600;">{{ $line->quantity }}x</td>
                                <td style="padding: .25rem .5rem;">{{ $line->name }}</td>
                                <td style="padding: .25rem 0 .25rem .5rem;" class="au-od-small">
                                    {{ collect([\Dashed\DashedEcommerceCore\Classes\OrderAddOns\Display::options($line), $line->sku])->filter()->implode(' · ') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
            <div style="font-weight: 700;">
                {{ trans_choice(':count extra item in deze doos|:count extra items in deze doos', $itemsCount, ['count' => $itemsCount]) }}
            </div>
        </div>
    @endif
</div>
