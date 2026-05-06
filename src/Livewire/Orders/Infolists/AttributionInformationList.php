<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

/**
 * Toont het herkomst-blok ("Herkomst") op de bestel-detailpagina. Geeft alle
 * UTM-velden, click-IDs, landingspagina, verwijzer, first/last touch en eventuele
 * extra parameters weer. Het blok wordt verborgen wanneer er geen attributie-data
 * is opgeslagen op de order (bijv. orders van voor de feature live ging).
 */
class AttributionInformationList extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Order $order;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function hasAttributionData(): bool
    {
        $fields = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'gclid',
            'fbclid',
            'msclkid',
            'landing_page',
            'landing_page_referrer',
            'attribution_first_touch_at',
            'attribution_last_touch_at',
        ];

        foreach ($fields as $field) {
            if (! empty($this->order->{$field})) {
                return true;
            }
        }

        $extra = $this->order->attribution_extra;

        return is_array($extra) && ! empty($extra);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->record($this->order)
            ->schema([
                Fieldset::make('Herkomst')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('utm_source')
                                    ->label('Bron')
                                    ->placeholder('Niet ingesteld')
                                    ->copyable(),
                                TextEntry::make('utm_medium')
                                    ->label('Medium')
                                    ->placeholder('Niet ingesteld')
                                    ->copyable(),
                                TextEntry::make('utm_campaign')
                                    ->label('Campagne')
                                    ->placeholder('Niet ingesteld')
                                    ->copyable(),
                                TextEntry::make('utm_term')
                                    ->label('Term')
                                    ->copyable()
                                    ->visible(fn ($record) => filled($record->utm_term)),
                                TextEntry::make('utm_content')
                                    ->label('Content')
                                    ->copyable()
                                    ->visible(fn ($record) => filled($record->utm_content)),
                                TextEntry::make('gclid')
                                    ->label('Google Click ID (gclid)')
                                    ->copyable()
                                    ->visible(fn ($record) => filled($record->gclid)),
                                TextEntry::make('fbclid')
                                    ->label('Facebook Click ID (fbclid)')
                                    ->copyable()
                                    ->visible(fn ($record) => filled($record->fbclid)),
                                TextEntry::make('msclkid')
                                    ->label('Microsoft Click ID (msclkid)')
                                    ->copyable()
                                    ->visible(fn ($record) => filled($record->msclkid)),
                            ]),
                        TextEntry::make('landing_page')
                            ->label('Landingspagina')
                            ->columnSpanFull()
                            ->copyable()
                            ->url(fn ($record) => $record->landing_page ?: null, shouldOpenInNewTab: true)
                            ->visible(fn ($record) => filled($record->landing_page)),
                        TextEntry::make('landing_page_referrer')
                            ->label('Verwijzer')
                            ->columnSpanFull()
                            ->copyable()
                            ->url(fn ($record) => $record->landing_page_referrer ?: null, shouldOpenInNewTab: true)
                            ->visible(fn ($record) => filled($record->landing_page_referrer)),
                        TextEntry::make('attribution_first_touch_at')
                            ->label('First-touch')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Onbekend'),
                        TextEntry::make('attribution_last_touch_at')
                            ->label('Last-touch')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('Onbekend'),
                        TextEntry::make('attribution_extra')
                            ->label('Extra parameters')
                            ->columnSpanFull()
                            ->getStateUsing(function ($record) {
                                $extra = $record->attribution_extra;
                                if (! is_array($extra) || empty($extra)) {
                                    return null;
                                }

                                return json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            })
                            ->visible(fn ($record) => is_array($record->attribution_extra) && ! empty($record->attribution_extra))
                            ->html()
                            ->formatStateUsing(fn ($state) => $state ? '<pre class="text-xs whitespace-pre-wrap break-all">'.e($state).'</pre>' : null),
                    ])
                    ->columns(2),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
