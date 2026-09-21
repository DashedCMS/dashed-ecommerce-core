<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountSettings;

class OnAccountSettingsPage extends Page
{
    use HasSettingsPermission;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-currency-euro';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Op rekening';

    protected static string | UnitEnum | null $navigationGroup = 'Systeem';

    protected static ?string $title = 'Op rekening';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $formData["on_account_default_term_days_{$id}"] = (int) Customsetting::get('on_account_default_term_days', $id, (string) OnAccountSettings::DEFAULT_TERM_DAYS);
            $formData["on_account_default_credit_limit_{$id}"] = Customsetting::get('on_account_default_credit_limit', $id);
            $formData["on_account_block_after_days_{$id}"] = OnAccountSettings::blockAfterDays($id);

            foreach (Locales::getLocales() as $locale) {
                $formData["on_account_reminder_stages_{$id}_{$locale['id']}"] = array_values(OnAccountSettings::reminderStages($id, $locale['id']));
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];

            $stageTabs = [];
            foreach (Locales::getLocales() as $locale) {
                $stageTabs[] = Tab::make($locale['id'])->label(strtoupper($locale['id']))->schema([
                    Repeater::make("on_account_reminder_stages_{$id}_{$locale['id']}")
                        ->label(__('Herinneringstappen'))
                        ->helperText(__('Dagen na de vervaldatum. Variabelen: :invoiceId:, :outstandingAmountFormatted:, :dueDate:, :daysOverdue:, :paymentUrl:, :customerFirstName:, :companyName:, :siteName:'))
                        ->schema([
                            TextInput::make('days')->label(__('Dagen na vervaldatum'))->numeric()->minValue(0)->required(),
                            TextInput::make('subject')->label(__('Onderwerp'))->required(),
                            Textarea::make('body')->label(__('Tekst'))->rows(4)->required(),
                        ])
                        ->defaultItems(0),
                ]);
            }

            $tabs[] = Tab::make($id)->label(ucfirst($site['name']))->schema([
                Section::make(__('Termijn en limiet'))->schema([
                    TextInput::make("on_account_default_term_days_{$id}")
                        ->label(__('Standaard betaaltermijn in dagen'))
                        ->numeric()->minValue(0)->required(),
                    TextInput::make("on_account_default_credit_limit_{$id}")
                        ->label(__('Standaard kredietlimiet'))
                        ->helperText(__('Leeg betekent geen limiet. Per klant te overschrijven.'))
                        ->numeric()->minValue(0),
                    TextInput::make("on_account_block_after_days_{$id}")
                        ->label(__('Blokkeren na zoveel dagen over de vervaldatum'))
                        ->helperText(__('0 betekent nooit automatisch blokkeren.'))
                        ->numeric()->minValue(0)->required(),
                ]),
                Tabs::make("on_account_stages_{$id}")->tabs($stageTabs),
            ]);
        }

        return $schema->schema([Tabs::make('Sites')->tabs($tabs)])->statePath('data');
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            Customsetting::set('on_account_default_term_days', (string) (int) $state["on_account_default_term_days_{$id}"], $id);
            Customsetting::set('on_account_default_credit_limit', $state["on_account_default_credit_limit_{$id}"] === null || $state["on_account_default_credit_limit_{$id}"] === '' ? null : (string) $state["on_account_default_credit_limit_{$id}"], $id);
            Customsetting::set('on_account_block_after_days', (string) (int) $state["on_account_block_after_days_{$id}"], $id);

            foreach (Locales::getLocales() as $locale) {
                Customsetting::set('on_account_reminder_stages', array_values($state["on_account_reminder_stages_{$id}_{$locale['id']}"] ?? []), $id, $locale['id']);
            }
        }

        Notification::make()->title(__('Instellingen opgeslagen'))->success()->send();
    }
}
