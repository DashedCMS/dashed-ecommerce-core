<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Services\Reorder\Reorder;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralSettings;

/**
 * Opnieuw bestellen en het verwijsprogramma, per site. Beide raken de
 * klant, dus beide staan standaard uit.
 */
class CustomerLoyaltySettingsPage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Klantenbinding';
    protected static string | UnitEnum | null $navigationGroup = 'Systeem';
    protected static ?string $title = 'Klantenbinding';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $formData[Reorder::SETTING . "_{$site['id']}"] = Reorder::enabled($site['id']);

            foreach (ReferralSettings::defaults() as $key => $default) {
                $formData["{$key}_{$site['id']}"] = Customsetting::get($key, $site['id'], $default);
            }

            $formData["referral_enabled_{$site['id']}"] = (bool) $formData["referral_enabled_{$site['id']}"];
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            $tabs[] = Tab::make($id)->label(ucfirst($site['name']))->schema([
                Section::make(__('Opnieuw bestellen'))->schema([
                    Toggle::make(Reorder::SETTING . "_{$id}")
                        ->label(__('Knop "Bestel opnieuw" aan'))
                        ->helperText(__('Zet de producten van een eerdere bestelling terug in de winkelwagen, vanuit het account of met de link :reorderUrl: en het mailblok "Bestel opnieuw" in de ordermails. Bestellingen van Bol en andere marktplaatsen doen nooit mee.')),
                ]),
                Section::make(__('Verwijsprogramma'))->schema([
                    Toggle::make("referral_enabled_{$id}")
                        ->label(__('Verwijsprogramma aan'))
                        ->helperText(__('Elke klant krijgt een eigen verwijslink. Een nieuwe klant krijgt korting op zijn eerste bestelling, de verwijzer een kortingscode zodra die bestelling betaald is en de wachttijd voorbij is.')),
                    Select::make("referral_friend_discount_type_{$id}")
                        ->label(__('Korting voor de nieuwe klant'))
                        ->options(['percentage' => __('Percentage'), 'amount' => __('Vast bedrag')])
                        ->required(),
                    TextInput::make("referral_friend_discount_value_{$id}")
                        ->label(__('Hoogte van de korting voor de nieuwe klant'))
                        ->numeric()->minValue(0)->required(),
                    TextInput::make("referral_friend_minimum_amount_{$id}")
                        ->label(__('Minimaal bestelbedrag voor de nieuwe klant'))
                        ->helperText(__('0 is geen minimum.'))
                        ->numeric()->minValue(0),
                    Select::make("referral_reward_type_{$id}")
                        ->label(__('Beloning voor de verwijzer'))
                        ->options(['percentage' => __('Percentage'), 'amount' => __('Vast bedrag')])
                        ->required(),
                    TextInput::make("referral_reward_value_{$id}")
                        ->label(__('Hoogte van de beloning'))
                        ->numeric()->minValue(0)->required(),
                    TextInput::make("referral_reward_valid_days_{$id}")
                        ->label(__('Beloning geldig (dagen)'))
                        ->numeric()->minValue(1)->required(),
                    TextInput::make("referral_wait_days_{$id}")
                        ->label(__('Wachttijd na betaling (dagen)'))
                        ->helperText(__('Houd dit gelijk aan de retourtermijn. Wordt de bestelling in die tijd geretourneerd of geannuleerd, dan krijgt de verwijzer niets.'))
                        ->numeric()->minValue(0)->required(),
                ]),
            ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            Customsetting::set(Reorder::SETTING, ($state[Reorder::SETTING . "_{$id}"] ?? false) ? 1 : 0, $id);

            foreach (ReferralSettings::defaults() as $key => $default) {
                $value = $state["{$key}_{$id}"] ?? $default;

                if ($key === 'referral_enabled') {
                    $value = $value ? 1 : 0;
                }

                Customsetting::set($key, $value, $id);
            }
        }

        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }
}
