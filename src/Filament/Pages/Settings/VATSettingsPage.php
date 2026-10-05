<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\KeyValue;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\OssVat;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedCore\Traits\HasSettingsPermission;

class VATSettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'BTW instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'BTW instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["taxes_prices_include_taxes_{$site['id']}"] = json_decode(Customsetting::get('taxes_prices_include_taxes', $site['id'], 1));
            $formData["oss_enabled_{$site['id']}"] = (bool) Customsetting::get('oss_enabled', $site['id']);
            $formData["oss_vat_rates_{$site['id']}"] = collect(OssVat::rates($site['id']))
                ->map(fn ($rate) => OssVat::rateKey($rate))
                ->all();
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $newSchema = [
                TextEntry::make('label')
                    ->state("BTW instellingen voor {$site['name']}"),
                Toggle::make("taxes_prices_include_taxes_{$site['id']}")
                    ->label(__('Alle prijzen zijn inclusief belasting'))
                    ->helperText(__('Indien dit aangevinkt staat wordt de opgegeven prijs bij een product gerekend als inclusief BTW. Indien dit staat uitgeschakeld wordt de BTW over de producten pas bij de checkout berekend.'))
                    ->required(),
                Toggle::make("oss_enabled_{$site['id']}")
                    ->label(__('OSS toepassen (btw van het bestemmingsland voor particulieren in andere EU-landen)'))
                    ->helperText(__('Verplicht vanaf € 10.000 omzet per jaar aan particulieren in andere EU-landen, of na een vrijwillige keuze voor de OSS-regeling. Nieuwe bestellingen naar een ander EU-land krijgen het btw-tarief van dat land; de klant betaalt hetzelfde bedrag. De verzamelfactuur toont deze omzet per land onder "OSS omzet". Werkt alleen als de prijzen inclusief belasting zijn ingesteld.'))
                    ->live(),
                KeyValue::make("oss_vat_rates_{$site['id']}")
                    ->label(__('OSS: standaardtarief per EU-land'))
                    ->helperText(__('Landcode (bijv. DE) en het standaard btw-tarief in procenten. Pas een regel aan als een land zijn tarief wijzigt.'))
                    ->keyLabel(__('Landcode'))
                    ->valueLabel(__('Tarief (%)'))
                    ->addable(false)
                    ->deletable(false)
                    ->editableKeys(false)
                    ->visible(fn (Get $get) => (bool) $get("oss_enabled_{$site['id']}"))
                    ->columnSpanFull(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($newSchema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $schema->schema($tabGroups)
            ->statePath('data');
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            $state = $this->form->getState();
            Customsetting::set('taxes_prices_include_taxes', $state["taxes_prices_include_taxes_{$site['id']}"], $site['id']);
            Customsetting::set('oss_enabled', ! empty($state["oss_enabled_{$site['id']}"]) ? '1' : '0', $site['id']);
            if (array_key_exists("oss_vat_rates_{$site['id']}", $state)) {
                Customsetting::set('oss_vat_rates', json_encode(static::overridesOnly($state["oss_vat_rates_{$site['id']}"] ?? []), JSON_FORCE_OBJECT), $site['id']);
            }
        }

        Notification::make()
            ->title(__('De BTW instellingen zijn opgeslagen'))
            ->success()
            ->send();
    }

    /**
     * @return array<string, float>
     */
    public static function normalizeRates(array $rates): array
    {
        $normalized = [];

        foreach ($rates as $code => $rate) {
            $code = strtoupper(trim((string) $code));
            $rate = str_replace(',', '.', trim((string) $rate));

            if (isset(OssVat::STANDARD_RATES[$code]) && is_numeric($rate)) {
                $normalized[$code] = (float) $rate;
            }
        }

        return $normalized;
    }

    /**
     * Alleen de tarieven die afwijken van de standaardtabel: zo bereikt een
     * nieuw standaardtarief in een release ook sites die al eens hebben
     * opgeslagen. Een leeg resultaat wist eerdere afwijkingen.
     *
     * @return array<string, float>
     */
    public static function overridesOnly(array $rates): array
    {
        return array_filter(
            static::normalizeRates($rates),
            fn (float $rate, string $code) => abs($rate - (float) OssVat::STANDARD_RATES[$code]) > 0.00001,
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
