<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaAudienceResource;
use Dashed\DashedEcommerceCore\Services\Meta\Audiences\MetaAudienceSettings;

class MetaAudienceSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasSettingsPermission;
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Doelgroepen naar Meta';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $settings = MetaAudienceSettings::for($id);
            $formData["meta_audiences_enabled_{$id}"] = $settings->enabled();
            $formData["meta_audiences_ad_account_id_{$id}"] = $settings->adAccountId();
            // Het token nooit terugtonen; leeg laten betekent "ongewijzigd".
            $formData["meta_audiences_access_token_{$id}"] = '';
        }

        $this->form->fill($formData);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('audiences')
                ->label(__('Doelgroepen beheren'))
                ->icon('heroicon-o-user-group')
                ->color('gray')
                ->url(MetaAudienceResource::getUrl()),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $settings = MetaAudienceSettings::for($id);

            $tabs[] = Tab::make($id)
                ->label(ucfirst($site['name']))
                ->schema([
                    Toggle::make("meta_audiences_enabled_{$id}")
                        ->label(__('Doelgroepen naar Meta synchroniseren'))
                        ->helperText(__('Zet nieuwsbrieflijsten, segmenten en prijsgroepen elke nacht als Custom Audience naar Meta, gehasht. Alleen contacten met een actieve aanmelding voor de nieuwsbrief gaan mee; geblokkeerde adressen en Bol-klanten nooit.')),
                    TextInput::make("meta_audiences_ad_account_id_{$id}")
                        ->label(__('Advertentieaccount-ID'))
                        ->placeholder('act_1234567890')
                        ->helperText(__('Te vinden in Ads Manager. Met of zonder act_ ervoor.')),
                    TextInput::make("meta_audiences_access_token_{$id}")
                        ->label(__('Toegangstoken'))
                        ->password()
                        ->revealable()
                        ->helperText($settings->ownAccessToken()
                            ? __('Er is een token opgeslagen. Leeg laten houdt het ongewijzigd.')
                            : (MetaCapiSettings::for($id)->hasAccessToken()
                                ? __('Leeg: het token van de Conversions API wordt gebruikt. Dat werkt alleen als het de rechten ads_management heeft; anders hier een token van een systeemgebruiker invullen.')
                                : __('Een token van een systeemgebruiker in Business Manager met de rechten ads_management op dit advertentieaccount.'))),
                    Placeholder::make("meta_audiences_status_{$id}")
                        ->label(__('Status'))
                        ->content($settings->ready()
                            ? __('Klaar om te synchroniseren.')
                            : __('Nog niet klaar: zet de schakelaar aan en vul een advertentieaccount en token in.')),
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
            Customsetting::set('meta_audiences_enabled', (bool) ($state["meta_audiences_enabled_{$id}"] ?? false), $id);
            Customsetting::set('meta_audiences_ad_account_id', trim((string) ($state["meta_audiences_ad_account_id_{$id}"] ?? '')) ?: null, $id);
            MetaAudienceSettings::for($id)->storeAccessToken($state["meta_audiences_access_token_{$id}"] ?? null);
        }

        Notification::make()
            ->title(__('De instellingen voor doelgroepen naar Meta zijn opgeslagen'))
            ->success()
            ->send();

        redirect(static::getUrl());
    }
}
