<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedCore\Traits\HasSettingsPermission;

class Gs1SettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'GS1 / EAN instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $title = 'GS1 / EAN instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    private const STRING_KEYS = [
        'gs1_contract_number',
        'gs1_default_classification',
        'gs1_default_packaging_type',
        'gs1_default_brand',
        'gs1_default_sub_brand',
        'gs1_default_language',
        'gs1_default_country',
        'gs1_default_unit',
    ];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            foreach (self::STRING_KEYS as $key) {
                $formData["{$key}_{$site['id']}"] = Customsetting::get($key, $site['id'], match ($key) {
                    'gs1_default_language' => 'Nederlands',
                    'gs1_default_country' => 'Nederland',
                    'gs1_default_unit' => 'Stuks',
                    default => null,
                });
            }
            $formData["gs1_default_quantity_{$site['id']}"] = Customsetting::get('gs1_default_quantity', $site['id'], 1);
            $formData["gs1_default_consumer_unit_{$site['id']}"] = (bool) Customsetting::get('gs1_default_consumer_unit', $site['id'], 1);
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ])
                ->schema([
                    TextEntry::make('label')
                        ->state("GS1 / EAN instellingen voor {$site['name']}")
                        ->columnSpan(2),
                    TextInput::make("gs1_contract_number_{$site['id']}")
                        ->label('GS1 contractnummer')
                        ->helperText('Wordt gebruikt voor de naamgeving van het export-bestand. Dit nummer staat ook op de contractsheet in mijnGS1.'),
                    TextInput::make("gs1_default_classification_{$site['id']}")
                        ->label('Productclassificatie (GPC)')
                        ->helperText('Bijvoorbeeld: Decoraties - Accessoires. Per productcategorie of product te overschrijven.'),
                    TextInput::make("gs1_default_packaging_type_{$site['id']}")
                        ->label('Verpakkingstype')
                        ->helperText('Bijvoorbeeld: Doos, Blister, Zak.'),
                    TextInput::make("gs1_default_brand_{$site['id']}")
                        ->label('Merk'),
                    TextInput::make("gs1_default_sub_brand_{$site['id']}")
                        ->label('Submerk'),
                    TextInput::make("gs1_default_language_{$site['id']}")
                        ->label('Taal')
                        ->default('Nederlands'),
                    TextInput::make("gs1_default_country_{$site['id']}")
                        ->label('Land')
                        ->default('Nederland'),
                    TextInput::make("gs1_default_quantity_{$site['id']}")
                        ->label('Aantal')
                        ->numeric()
                        ->default(1),
                    TextInput::make("gs1_default_unit_{$site['id']}")
                        ->label('Eenheid')
                        ->default('Stuks'),
                    Toggle::make("gs1_default_consumer_unit_{$site['id']}")
                        ->label('Consumenteneenheid (Ja/Nee)')
                        ->helperText('Een consumenteneenheid wordt aan de eindklant verkocht.'),
                ]);
        }

        return $schema
            ->schema([Tabs::make('Sites')->tabs($tabs)])
            ->statePath('data');
    }

    public function submit(): void
    {
        foreach (Sites::getSites() as $site) {
            foreach (self::STRING_KEYS as $key) {
                Customsetting::set($key, $this->form->getState()["{$key}_{$site['id']}"] ?? null, $site['id']);
            }
            Customsetting::set('gs1_default_quantity', (int) ($this->form->getState()["gs1_default_quantity_{$site['id']}"] ?? 1), $site['id']);
            Customsetting::set('gs1_default_consumer_unit', $this->form->getState()["gs1_default_consumer_unit_{$site['id']}"] ? 1 : 0, $site['id']);
        }

        Notification::make()
            ->title('Instellingen opgeslagen')
            ->success()
            ->send();
    }
}
