<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Concerns\InteractsWithForms;

class CheckoutSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Afreken instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Afreken instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["checkout_account_{$site['id']}"] = Customsetting::get('checkout_account', $site['id'], 'optional');
            $formData["checkout_form_name_{$site['id']}"] = Customsetting::get('checkout_form_name', $site['id'], 'last');
            $formData["checkout_form_company_name_{$site['id']}"] = Customsetting::get('checkout_form_company_name', $site['id'], 'hidden');
            //            $formData["checkout_form_address_line_2_{$site['id']}"] = Customsetting::get('checkout_form_address_line_2', $site['id'], 'optional');
            $formData["checkout_form_phone_number_delivery_address_{$site['id']}"] = Customsetting::get('checkout_form_phone_number_delivery_address', $site['id'], 'hidden');
            $formData["checkout_delivery_address_standard_invoice_address_{$site['id']}"] = Customsetting::get('checkout_delivery_address_standard_invoice_address', $site['id'], 1);
            $formData["checkout_autofill_address_{$site['id']}"] = Customsetting::get('checkout_autofill_address', $site['id'], 1);
            $formData["checkout_extra_scripts_{$site['id']}"] = Customsetting::get('checkout_extra_scripts', $site['id']);
            $formData["checkout_google_api_key_{$site['id']}"] = Customsetting::get('checkout_google_api_key', $site['id']);
            $formData["checkout_postnl_api_key_{$site['id']}"] = Customsetting::get('checkout_postnl_api_key', $site['id']);
            $formData["checkout_bcc_email_{$site['id']}"] = Customsetting::get('checkout_bcc_email', $site['id']);
            $formData["checkout_force_checkout_page_{$site['id']}"] = Customsetting::get('checkout_force_checkout_page', $site['id'], false);
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
                    ->label("Algemene afrekeninstellingen voor {$site['name']}")
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                Radio::make("checkout_account_{$site['id']}")
                    ->label('Klantaccounts')
                    ->options([
                        'disabled' => 'Accounts zijn uitgeschakeld',
                        'optional' => 'Accounts zijn optioneel',
                        'required' => 'Account vereist',
                    ])
                    ->required(),
                Radio::make("checkout_form_name_{$site['id']}")
                    ->label('Voor- en achternaam')
                    ->options([
                        'last' => 'Alleen achternaam nodig',
                        'full' => 'Voor- en achternaam vereisen',
                    ])
                    ->required(),
                Radio::make("checkout_form_company_name_{$site['id']}")
                    ->label('Bedrijfsnaam')
                    ->options([
                        'hidden' => 'Verborgen',
                        'optional' => 'Optioneel',
                        'required' => 'Verplicht',
                    ])
                    ->required(),
                Radio::make("checkout_form_phone_number_delivery_address_{$site['id']}")
                    ->label('Telefoonnummer van het bezorgadres')
                    ->options([
                        'hidden' => 'Verborgen',
                        'optional' => 'Optioneel',
                        'required' => 'Verplicht',
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
                    ->helperText('Dit wordt gebruikt voor het automatisch aanvullen van het adres van de klant. Dit werkt alleen met een nieuwe versie checkout.'),
                TextInput::make("checkout_bcc_email_{$site['id']}")
                    ->email()
                    ->label('BCC email om de bevestigingsmail naar te sturen'),
                Toggle::make("checkout_force_checkout_page_{$site['id']}")
                    ->label('Forceer checkout pagina vanaf de winkelwagen pagina')
                    ->helperText('Hiermee wordt de klant direct naar de checkout pagina gestuurd als ze naar de winkelwagen pagina gaan.'),
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

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('checkout_account', $this->form->getState()["checkout_account_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_name', $this->form->getState()["checkout_form_name_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_company_name', $this->form->getState()["checkout_form_company_name_{$site['id']}"], $site['id']);
            //            Customsetting::set('checkout_form_address_line_2', $this->form->getState()["checkout_form_address_line_2_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_form_phone_number_delivery_address', $this->form->getState()["checkout_form_phone_number_delivery_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_delivery_address_standard_invoice_address', $this->form->getState()["checkout_delivery_address_standard_invoice_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_autofill_address', $this->form->getState()["checkout_autofill_address_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_extra_scripts', $this->form->getState()["checkout_extra_scripts_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_google_api_key', $this->form->getState()["checkout_google_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_postnl_api_key', $this->form->getState()["checkout_postnl_api_key_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_bcc_email', $this->form->getState()["checkout_bcc_email_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_force_checkout_page', $this->form->getState()["checkout_force_checkout_page_{$site['id']}"], $site['id']);
        }

        $this->notify('success', 'De afreken instellingen zijn opgeslagen');
    }
}
