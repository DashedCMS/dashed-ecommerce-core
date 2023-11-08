<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;

class VATSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'BTW instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'BTW instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["taxes_prices_include_taxes_{$site['id']}"] = json_decode(Customsetting::get('taxes_prices_include_taxes', $site['id'], 1));
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("BTW instellingen voor {$site['name']}"),
                Toggle::make("taxes_prices_include_taxes_{$site['id']}")
                    ->label('Alle prijzen zijn inclusief belasting')
                    ->helperText('Indien dit aangevinkt staat wordt de opgegeven prijs bij een product gerekend als inclusief BTW. Indien dit staat uitgeschakeld wordt de BTW over de producten pas bij de checkout berekend.')
                    ->required(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('taxes_prices_include_taxes', $this->form->getState()["taxes_prices_include_taxes_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De BTW instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
