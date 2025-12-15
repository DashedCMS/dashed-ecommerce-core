<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;

class OrderSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Bestelling instellingen';
    protected static string|UnitEnum|null $navigationGroup = 'Overige';
    protected static ?string $title = 'Bestelling instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

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

        $formData["apis"] = Customsetting::get('apis', null, []);
        $formData["invoice_printer_connector_type"] = Customsetting::get('invoice_printer_connector_type', null, '');
        $formData["invoice_printer_connector_descriptor"] = Customsetting::get('invoice_printer_connector_descriptor', null, '');
        $formData["packing_slip_printer_connector_type"] = Customsetting::get('packing_slip_printer_connector_type', null, '');
        $formData["packing_slip_printer_connector_descriptor"] = Customsetting::get('packing_slip_printer_connector_descriptor', null, '');
        $formData["invoice_printer_connector_type"] = Customsetting::get('invoice_printer_connector_type', null, '');
        $formData["invoice_printer_connector_descriptor"] = Customsetting::get('invoice_printer_connector_descriptor', null, '');

        foreach ($locales as $locale) {
            foreach (Orders::getFulfillmentStatusses() as $fulfillmentStatus => $name) {
                $formData["fulfillment_status_{$fulfillmentStatus}_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_' . $fulfillmentStatus . '_enabled', null, false, $locale['id']) ? true : false;
                $formData["fulfillment_status_{$fulfillmentStatus}_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_' . $fulfillmentStatus . '_email_subject', null, null, $locale['id']);
                $formData["fulfillment_status_{$fulfillmentStatus}_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_' . $fulfillmentStatus . '_email_content', null, null, $locale['id']);
            }
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $sites = Sites::getSites();
        $locales = Locales::getLocales();
        $tabGroups = [];

        $apiFields = [];

        foreach (forms()->builder('orderApiClasses') as $api) {
            foreach ($api['class']::formFields() as $field) {
                $apiFields[] = $field
                    ->visible(fn (Get $get) => $get('class') == $api['class']);
            }
        }

        $newSchema = [
            Repeater::make('apis')
                ->label('APIs')
                ->helperText('Stel hier in welke APIs er bij een nieuwe bestelling aangeroepen moeten worden.')
                ->visible(count(forms()->builder('orderApiClasses')))
                ->reactive()
                ->schema(fn (Get $get) => array_merge([
                    Select::make('class')
                        ->label('API class')
                        ->options(collect(forms()->builder('orderApiClasses'))->pluck('name', 'class')->toArray())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->reactive(),
                ], $apiFields))
                ->columnSpanFull()
                ->addActionLabel('API toevoegen')
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]),
        ];

        $tabGroups[] = Section::make()->columnSpanFull()
            ->schema($newSchema)
            ->visible(count(forms()->builder('orderApiClasses')))
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema = [
            TextEntry::make('label')
                ->state("Algemene instelling voor bestellingen"),
            Section::make('Facturen printer')->columnSpanFull()
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
                        ->required(fn (Get $get) => $get("invoice_printer_connector_type"))
                        ->reactive()
                        ->helperText('Als je dit koppelt worden de facturen automatisch geprint als ze worden aangemaakt bij een nieuwe bestelling'),
                ])
                ->columns(2),
            Section::make('Pakbon printer')->columnSpanFull()
                ->schema([
                    Select::make("packing_slip_printer_connector_type")
                        ->options([
                            'cups' => 'cups',
                            'network' => 'network',
                            'windows' => 'windows',
                        ])
                        ->reactive()
                        ->label('Printer connectie type'),
                    TextInput::make("packing_slip_printer_connector_descriptor")
                        ->label('Naam van de printer')
                        ->required(fn (Get $get) => $get("packing_slip_printer_connector_type"))
                        ->reactive()
                        ->helperText('Als je dit koppelt worden de pakbonnen automatisch geprint als ze worden aangemaakt bij een nieuwe bestelling'),
                ])
                ->columns(2),
        ];

        $tabGroups[] = Section::make()->columnSpanFull()
            ->schema($newSchema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $tabs = [];
        foreach ($sites as $site) {
            $newSchema = [
                TextEntry::make("Notificaties voor bestellingen op {$site['name']}")
                    ->state('Stel extra opties in voor de notificaties.')
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
                ->schema($newSchema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        $tabs = [];
        foreach ($locales as $locale) {
            $newSchema = [
                TextEntry::make('label')
                    ->state("Fulfillment notificaties voor {$locale['name']}")
                    ->helperText('Je kan de volgende variablen gebruiken in de mails: ' . implode(', ', OrderVariableReplacer::getAvailableVariables())),
            ];

            foreach (Orders::getFulfillmentStatusses() as $fulfillmentStatus => $name) {
                $newSchema = array_merge($newSchema, [
                    Toggle::make("fulfillment_status_{$fulfillmentStatus}_enabled_{$locale['id']}")
                        ->label('Fulfillment status "' . $name . '" actie')
                        ->reactive()
                        ->columnSpan([
                            'default' => 1,
                            'lg' => 2,
                        ]),
                    TextInput::make("fulfillment_status_{$fulfillmentStatus}_email_subject_{$locale['id']}")
                        ->label('Fulfillment status "' . $name . '" mail onderwerp')
                        ->columnSpanFull()
                        ->hidden(fn ($get) => ! $get("fulfillment_status_{$fulfillmentStatus}_enabled_{$locale['id']}")),
                    cms()->editorField("fulfillment_status_{$fulfillmentStatus}_email_content_{$locale['id']}", 'Fulfillment status "' . $name . '" mail inhoud')
                        ->columnSpanFull()
                        ->hidden(fn ($get) => ! $get("fulfillment_status_{$fulfillmentStatus}_enabled_{$locale['id']}")),
                ]);
            }

            $tabs[] = Tab::make($locale['id'])
                ->label(ucfirst($locale['name']))
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

        Customsetting::set('apis', $this->form->getState()["apis"]);

        foreach ($locales as $locale) {
            foreach (Orders::getFulfillmentStatusses() as $fulfillmentStatus => $name) {
                Customsetting::set('fulfillment_status_' . $fulfillmentStatus . '_enabled', $this->form->getState()["fulfillment_status_{$fulfillmentStatus}_enabled_{$locale['id']}"], null, $locale['id']);
                Customsetting::set('fulfillment_status_' . $fulfillmentStatus . '_email_subject', $this->form->getState()["fulfillment_status_{$fulfillmentStatus}_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
                Customsetting::set('fulfillment_status_' . $fulfillmentStatus . '_email_content', $this->form->getState()["fulfillment_status_{$fulfillmentStatus}_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            }
        }

        Customsetting::set('invoice_printer_connector_type', $this->form->getState()["invoice_printer_connector_type"], $site['id']);
        Customsetting::set('invoice_printer_connector_descriptor', $this->form->getState()["invoice_printer_connector_descriptor"], $site['id']);
        Customsetting::set('packing_slip_printer_connector_type', $this->form->getState()["packing_slip_printer_connector_type"], $site['id']);
        Customsetting::set('packing_slip_printer_connector_descriptor', $this->form->getState()["packing_slip_printer_connector_descriptor"], $site['id']);
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
                ->visible(Customsetting::get('packing_slip_printer_connector_descriptor', null, false))
                ->action(function () {
                    $order = Order::isPaid()->latest()->first();

                    if (! $order) {
                        $this->error('No paid orders found to test with');

                        return;
                    }

                    $order->printInvoice();
                }),
            Action::make('testPackingSlipPrinter')
                ->label('Test pakbon printer')
                ->visible(Customsetting::get('packing_slip_printer_connector_descriptor', null, false))
                ->action(function () {
                    $order = Order::isPaid()->latest()->first();

                    if (! $order) {
                        $this->error('No paid orders found to test with');

                        return;
                    }

                    $order->printPackingSlip();
                }),
        ];
    }
}
