<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Get;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Dashed\DashedCore\Models\User;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Rawilk\Printing\Facades\Printing;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Section;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\ReceiptPrinter\ReceiptPrinter;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;

class OrderSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Bestelling instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Bestelling instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        $locales = Locales::getLocales();
        foreach ($sites as $site) {
            $formData["notification_invoice_emails_{$site['id']}"] = Customsetting::get('notification_invoice_emails', $site['id']);
            $formData["notification_low_stock_emails_{$site['id']}"] = Customsetting::get('notification_low_stock_emails', $site['id']);
        }

        $formData["order_index_show_other_statuses"] = Customsetting::get('order_index_show_other_statuses', null, true) ? true : false;
        $formData["order_index_show_order_products"] = Customsetting::get('order_index_show_order_products', null, false) ? true : false;
        $formData["invoice_printer_connector_type"] = Customsetting::get('invoice_printer_connector_type', null, '');
        $formData["invoice_printer_connector_descriptor"] = Customsetting::get('invoice_printer_connector_descriptor', null, '');

        foreach ($locales as $locale) {
            $formData["fulfillment_status_unhandled_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_unhandled_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_unhandled_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_in_treatment_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_in_treatment_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_in_treatment_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_packed_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_packed_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_packed_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_shipped_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_shipped_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_shipped_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_handled_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_handled_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_handled_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_email_content', null, null, $locale['id']);
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $locales = Locales::getLocales();
        $tabGroups = [];

        $schema = [
            Placeholder::make('label')
                ->label("Algemene instelling voor bestellingen"),
            Toggle::make("order_index_show_other_statuses")
                ->label('Toon de extra statussen op het bestellingsoverzicht'),
            Toggle::make("order_index_show_order_products")
                ->label('Toon de bestelde producten op het bestellingsoverzicht'),
            Section::make('Facturen printer')
                ->schema([
                    Select::make("invoice_printer_connector_type")
                        ->options([
                            'cups' => 'cups',
                            'network' => 'network',
                            'windows' => 'windows',
                        ])
                        ->reactive()
                        ->label('Printer connectie type'),
                    TextInput::make("invoice_printer_connector_descriptor")
                        ->label('Naam van de printer')
                        ->required(fn (Get $get) => $get("receipt_printer_connector_type")),
                ])
                ->columns(2),
        ];

        $tabGroups[] = Card::make()
            ->schema($schema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("Notificaties voor bestellingen op {$site['name']}")
                    ->content('Stel extra opties in voor de notificaties.')
                    ->columnSpan(2),
                TagsInput::make("notification_invoice_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om de bevestigingsmail van een bestelling naar te sturen')
                    ->placeholder('Voer een email in')
                    ->reactive(),
                TagsInput::make("notification_low_stock_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om de notificaties van lage voorraad naartoe te sturen')
                    ->placeholder('Voer een email in')
                    ->reactive(),
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

        $tabs = [];
        foreach ($locales as $locale) {
            $schema = [
                Placeholder::make('label')
                    ->label("Fulfillment notificaties voor {$locale['name']}")
                    ->helperText('Je kan de volgende variablen gebruiken in de mails: :firstName:, :lastName:, :email:, :phoneNumber:, :street:, :houseNr:, :zipCode:, :city:, :country:, :companyName:, :total:'),
                Toggle::make("fulfillment_status_unhandled_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_unhandled_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" mail onderwerp')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_unhandled_enabled_{$locale['id']}")),
                TiptapEditor::make("fulfillment_status_unhandled_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" mail inhoud')
                    ->directory('/dashed/orders/images')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_unhandled_enabled_{$locale['id']}")),
                Toggle::make("fulfillment_status_in_treatment_enabled_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_in_treatment_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" mail onderwerp')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_in_treatment_enabled_{$locale['id']}")),
                TiptapEditor::make("fulfillment_status_in_treatment_email_content_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" mail inhoud')
                    ->directory('/dashed/orders/images')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_in_treatment_enabled_{$locale['id']}")),
                Toggle::make("fulfillment_status_packed_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_packed_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" mail onderwerp')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_packed_enabled_{$locale['id']}")),
                TiptapEditor::make("fulfillment_status_packed_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" mail inhoud')
                    ->directory('/dashed/orders/images')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_packed_enabled_{$locale['id']}")),
                Toggle::make("fulfillment_status_shipped_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_shipped_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" mail onderwerp')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_shipped_enabled_{$locale['id']}")),
                TiptapEditor::make("fulfillment_status_shipped_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" mail inhoud')
                    ->directory('/dashed/orders/images')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_shipped_enabled_{$locale['id']}")),
                Toggle::make("fulfillment_status_handled_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_handled_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" mail onderwerp')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_handled_enabled_{$locale['id']}")),
                TiptapEditor::make("fulfillment_status_handled_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" mail inhoud')
                    ->directory('/dashed/orders/images')
                    ->hidden(fn ($get) => ! $get("fulfillment_status_handled_enabled_{$locale['id']}")),
            ];

            $tabs[] = Tab::make($locale['id'])
                ->label(ucfirst($locale['name']))
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
        $locales = Locales::getLocales();
        $formState = $this->form->getState();

        foreach ($sites as $site) {
            $emails = $this->form->getState()["notification_invoice_emails_{$site['id']}"];
            foreach ($emails ?? [] as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_invoice_emails', $emails, $site['id']);
            $formState["notification_invoice_emails_{$site['id']}"] = $emails;

            $emails = $this->form->getState()["notification_low_stock_emails_{$site['id']}"];
            foreach ($emails ?? [] as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_low_stock_emails', $emails, $site['id']);
            $formState["notification_low_stock_emails_{$site['id']}"] = $emails;
        }

        Customsetting::set('order_index_show_other_statuses', $this->form->getState()["order_index_show_other_statuses"]);
        Customsetting::set('order_index_show_order_products', $this->form->getState()["order_index_show_order_products"]);

        foreach ($locales as $locale) {
            Customsetting::set('fulfillment_status_unhandled_enabled', $this->form->getState()["fulfillment_status_unhandled_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_unhandled_email_subject', $this->form->getState()["fulfillment_status_unhandled_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_unhandled_email_content', $this->form->getState()["fulfillment_status_unhandled_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_enabled', $this->form->getState()["fulfillment_status_in_treatment_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_email_subject', $this->form->getState()["fulfillment_status_in_treatment_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_email_content', $this->form->getState()["fulfillment_status_in_treatment_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_enabled', $this->form->getState()["fulfillment_status_packed_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_email_subject', $this->form->getState()["fulfillment_status_packed_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_email_content', $this->form->getState()["fulfillment_status_packed_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_enabled', $this->form->getState()["fulfillment_status_shipped_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_email_subject', $this->form->getState()["fulfillment_status_shipped_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_email_content', $this->form->getState()["fulfillment_status_shipped_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_enabled', $this->form->getState()["fulfillment_status_handled_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_email_subject', $this->form->getState()["fulfillment_status_handled_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_email_content', $this->form->getState()["fulfillment_status_handled_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
        }

        Customsetting::set('invoice_printer_connector_type', $this->form->getState()["invoice_printer_connector_type"], $site['id']);
        Customsetting::set('invoice_printer_connector_descriptor', $this->form->getState()["invoice_printer_connector_descriptor"], $site['id']);

        $this->form->fill($formState);

        Notification::make()
            ->title('De bestellings instellingen zijn opgeslagen')
            ->success()
            ->send();
    }

    protected function getActions(): array
    {
        return [
            Action::make('testInvoicePrinter')
                ->label('Test factuur printer')
                ->visible(Customsetting::get('invoice_printer_connector_type', null, false))
                ->action(function () {
                    $printers = Printing::printers();

                    foreach ($printers as $printer) {
//                        try {
                            //                            $printer = new ReceiptPrinter();
                            //                            $printer->init(
                            //                                Customsetting::get('invoice_printer_connector_type'),
                            //                                Customsetting::get('invoice_printer_connector_descriptor')
                            //                            );

                            $printJob = Printing::newPrintTask()
                                ->printer($printer->id())
                                ->file(public_path('test.pdf'))
                                ->send();

                            dd($printJob->id()); // the id number returned from the print server

//                        } catch (\Exception $e) {
//                            Notification::make()
//                                ->title('Er is een fout opgetreden')
//                                ->body($e->getMessage())
//                                ->danger()
//                                ->send();
//                        }
                    }
                }),
        ];
    }
}
