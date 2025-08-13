<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;

class DefaultEcommerceSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-report';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Facturatie instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Facturatie instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["google_merchant_center_id_{$site['id']}"] = Customsetting::get('google_merchant_center_id', $site['id']);
            $formData["enable_google_merchant_center_review_survey_{$site['id']}"] = Customsetting::get('enable_google_merchant_center_review_survey', $site['id']);
            $formData["enable_google_merchant_center_review_badge_{$site['id']}"] = Customsetting::get('enable_google_merchant_center_review_badge', $site['id']);
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
                TextInput::make("google_merchant_center_id_{$site['id']}")
                    ->label('Google Merchant Center ID')
                    ->columnSpanFull(),
                Toggle::make("enable_google_merchant_center_review_survey_{$site['id']}")
                    ->label('Google Merchant Center Review Survey aanzetten')
                    ->columnSpanFull(),
                Toggle::make("enable_google_merchant_center_review_badge_{$site['id']}")
                    ->label('Google Merchant Center Review Badge aanzetten')
                    ->columnSpanFull(),
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
            Customsetting::set('google_merchant_center_id', $this->form->getState()["google_merchant_center_id_{$site['id']}"], $site['id']);
            Customsetting::set('enable_google_merchant_center_review_survey', $this->form->getState()["enable_google_merchant_center_review_survey_{$site['id']}"], $site['id']);
            Customsetting::set('enable_google_merchant_center_review_badge', $this->form->getState()["enable_google_merchant_center_review_badge_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De algemene ecommerce instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
