<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Forms\Components\Select;
use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;

class OrderCancelSettingsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Annuleer bestelling instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Overige';
    protected static ?string $title = 'Annuleer bestelling instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["order_cancel_default_fulfillment_status_{$site['id']}"] = Customsetting::get('order_cancel_default_fulfillment_status', $site['id'], 'handled');
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
                    ->state("Bestelling annuleren instellingen voor {$site['name']}"),
                Select::make("order_cancel_default_fulfillment_status_{$site['id']}")
                    ->label('Verander fulfillment status naar')
                    ->options(array_merge([
                        '' => 'Leeg'
                    ], Orders::getFulfillmentStatusses())),
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
            Customsetting::set('order_cancel_default_fulfillment_status', $this->form->getState()["order_cancel_default_fulfillment_status_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De annuleer bestelling instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
