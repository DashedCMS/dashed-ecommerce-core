<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaCapiEventResource;

class MetaCapiSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasSettingsPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Meta Conversions API';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $settings = MetaCapiSettings::for($id);
            $formData["meta_capi_enabled_{$id}"] = $settings->enabled();
            // Het token nooit terugtonen; leeg laten betekent "ongewijzigd".
            $formData["meta_capi_access_token_{$id}"] = '';
            $formData["meta_capi_test_event_code_{$id}"] = $settings->testEventCode();
            $formData["meta_capi_value_mode_{$id}"] = $settings->valueMode();
        }

        $this->form->fill($formData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('log')
                ->label(__('Bekijk log'))
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(MetaCapiEventResource::getUrl()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $settings = MetaCapiSettings::for($id);

            $tabs[] = Tab::make($id)
                ->label(ucfirst($site['name']))
                ->schema([
                    Placeholder::make("meta_capi_pixel_{$id}")
                        ->label(__('Pixel-ID'))
                        ->content($settings->pixelId() ?: __('Nog geen pixel-ID ingesteld bij Algemene instellingen.')),
                    Toggle::make("meta_capi_enabled_{$id}")
                        ->label(__('Conversions API inschakelen'))
                        ->helperText(__('Stuurt elke betaalde webshop-order ook vanaf de server naar Meta. De browserpixel blijft werken; Meta ontdubbelt de twee.')),
                    TextInput::make("meta_capi_access_token_{$id}")
                        ->label(__('Toegangstoken'))
                        ->password()
                        ->revealable()
                        ->helperText($settings->hasAccessToken()
                            ? __('Er is een token opgeslagen. Leeg laten houdt het ongewijzigd.')
                            : __('Genereer het token in Events Manager bij de instellingen van de pixel.')),
                    TextInput::make("meta_capi_test_event_code_{$id}")
                        ->label(__('Test event code'))
                        ->helperText(__('Alleen invullen tijdens het testen. Zolang hier iets staat, komen alle events in het tabblad Test events en tellen ze niet mee.')),
                    Select::make("meta_capi_value_mode_{$id}")
                        ->label(__('Waarde van een aankoop'))
                        ->options([
                            MetaCapiSettings::VALUE_INCL_VAT => __('Inclusief btw en verzendkosten (zoals de pixel nu)'),
                            MetaCapiSettings::VALUE_EXCL_VAT => __('Exclusief btw, inclusief verzendkosten'),
                            MetaCapiSettings::VALUE_EXCL_VAT_EXCL_SHIPPING => __('Exclusief btw en verzendkosten'),
                        ])
                        ->selectablePlaceholder(false)
                        ->helperText(__('Geldt voor de server én de browserpixel. Wijzigen maakt de ROAS onvergelijkbaar met eerdere periodes.')),
                ]);
        }

        return $schema
            ->schema([Tabs::make('Sites')->tabs($tabs)])
            ->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            Customsetting::set('meta_capi_enabled', (bool) ($state["meta_capi_enabled_{$id}"] ?? false), $id);
            Customsetting::set('meta_capi_test_event_code', trim((string) ($state["meta_capi_test_event_code_{$id}"] ?? '')) ?: null, $id);
            Customsetting::set('meta_capi_value_mode', $state["meta_capi_value_mode_{$id}"] ?? MetaCapiSettings::VALUE_INCL_VAT, $id);
            MetaCapiSettings::for($id)->storeAccessToken($state["meta_capi_access_token_{$id}"] ?? null);
        }

        Notification::make()
            ->title(__('De Meta Conversions API-instellingen zijn opgeslagen'))
            ->success()
            ->send();

        redirect(static::getUrl());
    }
}
