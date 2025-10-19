<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Enums\CurrencyShowTypes;

class CheckoutSettingsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Afreken instellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Overige';
    protected static ?string $title = 'Afreken instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["checkout_account_{$site['id']}"] = Customsetting::get('checkout_account', $site['id'], 2);
            $formData["checkout_form_name_{$site['id']}"] = Customsetting::get('checkout_form_name', $site['id'], 0);
            $formData["checkout_form_company_name_{$site['id']}"] = Customsetting::get('checkout_form_company_name', $site['id'], 2);
            $formData["checkout_form_phone_number_delivery_address_{$site['id']}"] = Customsetting::get('checkout_form_phone_number_delivery_address', $site['id'], 0);
            $formData["checkout_delivery_address_standard_invoice_address_{$site['id']}"] = Customsetting::get('checkout_delivery_address_standard_invoice_address', $site['id'], 1);
            $formData["checkout_autofill_address_{$site['id']}"] = Customsetting::get('checkout_autofill_address', $site['id'], 1);
            $formData["checkout_extra_scripts_{$site['id']}"] = Customsetting::get('checkout_extra_scripts', $site['id']);
            $formData["checkout_google_api_key_{$site['id']}"] = Customsetting::get('checkout_google_api_key', $site['id']);
            $formData["checkout_postnl_api_key_{$site['id']}"] = Customsetting::get('checkout_postnl_api_key', $site['id']);
            $formData["checkout_postcode_api_key_{$site['id']}"] = Customsetting::get('checkout_postcode_api_key', $site['id']);
            $formData["checkout_bcc_email_{$site['id']}"] = Customsetting::get('checkout_bcc_email', $site['id']);
            $formData["checkout_force_checkout_page_{$site['id']}"] = Customsetting::get('checkout_force_checkout_page', $site['id'], false);
            $formData["currency_format_type_{$site['id']}"] = Customsetting::get('currency_format_type', $site['id'], 'type1');
            $formData["show_currency_symbol_{$site['id']}"] = Customsetting::get('show_currency_symbol', $site['id'], true);
            $formData["first_payment_method_selected_{$site['id']}"] = Customsetting::get('first_payment_method_selected', $site['id'], true);
            $formData["first_shipping_method_selected_{$site['id']}"] = Customsetting::get('first_shipping_method_selected', $site['id'], true);
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
                    ->state("Algemene afrekeninstellingen voor {$site['name']}")
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Radio::make("checkout_account_{$site['id']}")
                    ->label('Klantaccounts')
                    ->options([
                        0 => 'Accounts zijn uitgeschakeld',
                        2 => 'Accounts zijn optioneel',
                        1 => 'Account vereist',
                    ])
                    ->required(),
                Radio::make("checkout_form_name_{$site['id']}")
                    ->label('Voor- en achternaam')
                    ->options([
                        0 => 'Alleen achternaam nodig',
                        1 => 'Voor- en achternaam vereisen',
                    ])
                    ->required(),
                Radio::make("checkout_form_company_name_{$site['id']}")
                    ->label('Bedrijfsnaam')
                    ->options([
                        0 => 'Verborgen',
                        2 => 'Optioneel',
                        1 => 'Verplicht',
                    ])
                    ->required(),
                Radio::make("checkout_form_phone_number_delivery_address_{$site['id']}")
                    ->label('Telefoonnummer van het bezorgadres')
                    ->options([
                        0 => 'Verborgen',
                        2 => 'Optioneel',
                        1 => 'Verplicht',
                    ])
                    ->required(),
                Toggle::make("checkout_delivery_address_standard_invoice_address_{$site['id']}")
                    ->label('Het bezorgadres standaard als het factuuradres gebruiken')
                    ->helperText('Reduceer het aantal velden dat is vereist om af te rekenen. Het factuuradres kan nog steeds worden bewerkt.'),
                Toggle::make("checkout_autofill_address_{$site['id']}")
                    ->label('Automatisch aanvullen van adresgegevens inschakelen')
                    ->helperText('Hiermee wordt het adres van klanten automatisch aangevuld op basis van de Google API of PostNL API om zo het invullen soepeler te laten verlopen.'),
                Textarea::make("checkout_extra_scripts_{$site['id']}")
                    ->label('Aanvullende scripts')
                    ->rows(5)
                    ->helperText('Specifieke scripts die ingeladen moeten worden op de bestelstatus pagina van de bestelling.')
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("checkout_google_api_key_{$site['id']}")
                    ->label('Google Maps API key')
                    ->helperText('Dit wordt gebruikt voor het automatisch aanvullen van het adres van de klant. Dit werkt alleen met een oude versie checkout.'),
                TextInput::make("checkout_postnl_api_key_{$site['id']}")
                    ->label('PostNL API key')
                    ->hintAction(Action::make('requestPostcodeApi')
                        ->label('API aanvragen')
                        ->url('https://developer.postnl.nl/integration-with-postnl/request-api-key/')
                        ->openUrlInNewTab())
                    ->helperText('Dit wordt gebruikt voor het automatisch aanvullen van het adres van de klant. Dit werkt alleen met een nieuwe versie checkout.'),
                TextInput::make("checkout_postcode_api_key_{$site['id']}")
                    ->label('Postcode API key')
                    ->hintAction(Action::make('requestPostcodeApi')
                        ->label('API aanvragen')
                        ->url('https://postcode.tech/')
                        ->openUrlInNewTab())
                    ->helperText('Dit wordt gebruikt voor het automatisch aanvullen van het adres van de klant. Dit werkt alleen met een nieuwe versie checkout.'),
                TextInput::make("checkout_bcc_email_{$site['id']}")
                    ->email()
                    ->label('BCC email om de bevestigingsmail naar te sturen'),
                Toggle::make("checkout_force_checkout_page_{$site['id']}")
                    ->label('Forceer checkout pagina vanaf de winkelwagen pagina')
                    ->helperText('Hiermee wordt de klant direct naar de checkout pagina gestuurd als ze naar de winkelwagen pagina gaan.'),
                Radio::make("currency_format_type_{$site['id']}")
                    ->label('Bedragen weergeven als')
                    ->options(function () {
                        $options = [];

                        foreach (CurrencyShowTypes::cases() as $currencyShowType) {
                            $options[$currencyShowType->value] = $currencyShowType->getValue(10);
                        }

                        return $options;
                    })
                    ->required(),
                Toggle::make("show_currency_symbol_{$site['id']}")
                    ->label('Laat valutasymbool zien'),
                Toggle::make("first_payment_method_selected_{$site['id']}")
                    ->label('Eerste betaalmethode standaard geselecteerd'),
                Toggle::make("first_shipping_method_selected_{$site['id']}")
                    ->label('Eerste verzendmethode standaard geselecteerd'),
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
            Customsetting::set('checkout_account', $this->form->getState()["checkout_account_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_name', $this->form->getState()["checkout_form_name_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_company_name', $this->form->getState()["checkout_form_company_name_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_phone_number_delivery_address', $this->form->getState()["checkout_form_phone_number_delivery_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_delivery_address_standard_invoice_address', $this->form->getState()["checkout_delivery_address_standard_invoice_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_autofill_address', $this->form->getState()["checkout_autofill_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_extra_scripts', $this->form->getState()["checkout_extra_scripts_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_google_api_key', $this->form->getState()["checkout_google_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_postnl_api_key', $this->form->getState()["checkout_postnl_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_postcode_api_key', $this->form->getState()["checkout_postcode_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_bcc_email', $this->form->getState()["checkout_bcc_email_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_force_checkout_page', $this->form->getState()["checkout_force_checkout_page_{$site['id']}"], $site['id']);
            Customsetting::set('currency_format_type', $this->form->getState()["currency_format_type_{$site['id']}"], $site['id']);
            Customsetting::set('show_currency_symbol', $this->form->getState()["show_currency_symbol_{$site['id']}"], $site['id']);
            Customsetting::set('first_payment_method_selected', $this->form->getState()["first_payment_method_selected_{$site['id']}"], $site['id']);
            Customsetting::set('first_shipping_method_selected', $this->form->getState()["first_shipping_method_selected_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De afreken instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
