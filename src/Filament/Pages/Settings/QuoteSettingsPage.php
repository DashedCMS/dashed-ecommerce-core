<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteDefaults;

class QuoteSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Offerte instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Offerte instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $formData["quote_number_format_{$site['id']}"] = QuoteDefaults::numberFormat($site['id']);
            $formData["current_quote_number_{$site['id']}"] = Customsetting::get('current_quote_number', $site['id'], 1000);
            $formData["quote_validity_days_{$site['id']}"] = QuoteDefaults::validityDays($site['id']);
            $formData["quote_from_email_{$site['id']}"] = Customsetting::get('quote_from_email', $site['id'], '');
            $formData["quote_reminder_enabled_{$site['id']}"] = QuoteDefaults::reminderEnabled($site['id']);
            $formData["quote_reminder_days_before_{$site['id']}"] = QuoteDefaults::reminderDaysBefore($site['id']);

            foreach ($this->locales($site['id']) as $locale) {
                foreach (['intro', 'terms', 'acceptance'] as $key) {
                    $formData["quote_{$key}_{$locale}_{$site['id']}"] = QuoteDefaults::text($key, $locale, $site['id']);
                }
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (Sites::getSites() as $site) {
            $fields = [
                TextInput::make("quote_number_format_{$site['id']}")
                    ->label(__('Nummerformaat'))
                    ->helperText(__('Plaatshouders: :jaar:, :maand: en :nummer:'))
                    ->maxLength(50),
                TextInput::make("current_quote_number_{$site['id']}")
                    ->label(__('Huidig offertenummer'))
                    ->numeric(),
                TextInput::make("quote_validity_days_{$site['id']}")
                    ->label(__('Standaard geldig voor (dagen)'))
                    ->numeric()
                    ->minValue(1),
                TextInput::make("quote_from_email_{$site['id']}")
                    ->label(__('Afzenderadres voor offertemails'))
                    ->email()
                    ->helperText(__('Leeg laten om het algemene afzenderadres van de site te gebruiken')),
                Toggle::make("quote_reminder_enabled_{$site['id']}")
                    ->label(__('Herinnering sturen voordat een offerte verloopt'))
                    ->reactive(),
                TextInput::make("quote_reminder_days_before_{$site['id']}")
                    ->label(__('Aantal dagen voor het verlopen'))
                    ->numeric()
                    ->minValue(1)
                    ->hidden(fn ($get) => ! $get("quote_reminder_enabled_{$site['id']}")),
            ];

            foreach ($this->locales($site['id']) as $locale) {
                $fields[] = Textarea::make("quote_intro_{$locale}_{$site['id']}")
                    ->label(__('Standaard intro').' ('.strtoupper($locale).')')
                    ->rows(3)
                    ->columnSpanFull();
                $fields[] = Textarea::make("quote_terms_{$locale}_{$site['id']}")
                    ->label(__('Standaard planning en voorwaarden').' ('.strtoupper($locale).')')
                    ->rows(6)
                    ->columnSpanFull();
                $fields[] = Textarea::make("quote_acceptance_{$locale}_{$site['id']}")
                    ->label(__('Standaard akkoordtekst').' ('.strtoupper($locale).')')
                    ->rows(3)
                    ->columnSpanFull();
            }

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($fields)
                ->columns(['default' => 1, 'lg' => 2]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            foreach ([
                'quote_number_format',
                'current_quote_number',
                'quote_validity_days',
                'quote_from_email',
                'quote_reminder_enabled',
                'quote_reminder_days_before',
            ] as $key) {
                Customsetting::set($key, $state["{$key}_{$site['id']}"] ?? null, $site['id']);
            }

            foreach ($this->locales($site['id']) as $locale) {
                foreach (['intro', 'terms', 'acceptance'] as $key) {
                    Customsetting::set("quote_{$key}_{$locale}", $state["quote_{$key}_{$locale}_{$site['id']}"] ?? '', $site['id']);
                }
            }
        }

        Notification::make()
            ->title(__('De offerte instellingen zijn opgeslagen'))
            ->success()
            ->send();
    }

    /** @return array<int, string> */
    private function locales(string $siteId): array
    {
        return array_column(\Dashed\DashedCore\Classes\Locales::getLocalesForSite($siteId), 'id');
    }
}
