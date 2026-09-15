<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

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
use Dashed\DashedCore\Traits\HasSettingsPermission;

class WishlistSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-heart';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Verlanglijst';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Verlanglijst';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $formData["wishlist_enabled_{$site['id']}"] = (bool) Customsetting::get('wishlist_enabled', $site['id'], 1);
        }
        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $tabs[] = Tab::make($site['id'])->label(ucfirst($site['name']))->schema([
                Toggle::make("wishlist_enabled_{$site['id']}")
                    ->label(__('Verlanglijst aan'))
                    ->helperText(__('Uit: hartjes en teller verdwijnen, de lijstpagina toont de lege staat. Opgeslagen lijsten blijven bewaard.')),
            ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();
        foreach (Sites::getSites() as $site) {
            Customsetting::set('wishlist_enabled', $state["wishlist_enabled_{$site['id']}"] ? 1 : 0, $site['id']);
        }
        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }
}
