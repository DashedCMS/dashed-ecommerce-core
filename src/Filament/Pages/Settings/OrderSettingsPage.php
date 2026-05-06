<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Schema as DBSchema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedEcommerceCore\Classes\OrderOrigins;
use Dashed\DashedEcommerceCore\Jobs\BackfillApiSubscriptionsJob;
use Dashed\DashedCore\Notifications\NotificationChannels;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;

class OrderSettingsPage extends Page
{
    use HasSettingsPermission;

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
            $formData["notification_bcc_order_emails_{$site['id']}"] = Customsetting::get('notification_bcc_order_emails', $site['id']);

            $overridesForSite = Customsetting::get('admin_notify_per_order_origin', $site['id'], []);
            $overridesForSite = is_array($overridesForSite) ? $overridesForSite : [];
            $channels = NotificationChannels::all();
            foreach (OrderOrigins::all($site['id']) as $origin) {
                $originOverride = $overridesForSite[$origin['key']] ?? null;
                foreach ($channels as $channel) {
                    $formKey = "admin_notify_origin_{$site['id']}_{$origin['key']}_{$channel['key']}";

                    if (is_bool($originOverride)) {
                        $formData[$formKey] = $originOverride;

                        continue;
                    }

                    if (is_array($originOverride) && array_key_exists($channel['key'], $originOverride)) {
                        $formData[$formKey] = (bool) $originOverride[$channel['key']];

                        continue;
                    }

                    $formData[$formKey] = $origin['default_notify'];
                }
            }

            $formData["cart_suggestions_enabled_{$site['id']}"] = filter_var(Customsetting::get('cart_suggestions_enabled', $site['id'], '1'), FILTER_VALIDATE_BOOLEAN);
            $formData["cart_suggestions_limit_cart_{$site['id']}"] = (int) Customsetting::get('cart_suggestions_limit_cart', $site['id'], '6');
            $formData["cart_suggestions_limit_checkout_{$site['id']}"] = (int) Customsetting::get('cart_suggestions_limit_checkout', $site['id'], '4');
            $formData["cart_suggestions_limit_popup_{$site['id']}"] = (int) Customsetting::get('cart_suggestions_limit_popup', $site['id'], '3');
            $formData["cart_suggestions_boost_slots_{$site['id']}"] = (int) Customsetting::get('cart_suggestions_boost_slots', $site['id'], '3');
            $formData["cart_suggestions_gap_min_factor_{$site['id']}"] = (float) Customsetting::get('cart_suggestions_gap_min_factor', $site['id'], '0.8');
            $formData["cart_suggestions_gap_max_factor_{$site['id']}"] = (float) Customsetting::get('cart_suggestions_gap_max_factor', $site['id'], '1.5');
            $formData["cart_suggestions_require_in_stock_{$site['id']}"] = filter_var(Customsetting::get('cart_suggestions_require_in_stock', $site['id'], '1'), FILTER_VALIDATE_BOOLEAN);
            $formData["cart_suggestions_fallback_random_{$site['id']}"] = filter_var(Customsetting::get('cart_suggestions_fallback_random', $site['id'], '1'), FILTER_VALIDATE_BOOLEAN);
        }

        $formData["apis"] = Customsetting::get('apis', null, []);
        $formData["attribution_tracking_enabled"] = (bool) Customsetting::get('attribution_tracking_enabled', null, true);
        $formData["attribution_show_on_invoice"] = (bool) Customsetting::get('attribution_show_on_invoice', null, false);
        $formData["order_handled_flow_review_url"] = (string) Customsetting::get('order_handled_flow_review_url', null, '');
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
                    Toggle::make('sync_always')
                        ->label('Altijd synchroniseren (ook zonder marketing-toestemming)')
                        ->helperText('Standaard wordt deze API alleen aangeroepen als de klant in de checkout marketing-toestemming heeft gegeven. Zet aan om altijd te syncen.')
                        ->default(false)
                        ->columnSpanFull(),
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
            Section::make('UTM- / herkomst-tracking')
                ->description('Vangt UTM-parameters (utm_source, utm_medium, ...) en click-IDs (gclid, fbclid, msclkid) automatisch op uit de querystring zodra een bezoeker op de webshop landt. Deze waardes worden bewaard op de winkelwagen en bij plaatsing op de bestelling, zodat je per bestelling kunt zien via welk kanaal of welke campagne een klant binnenkwam.')
                ->columnSpanFull()
                ->schema([
                    Toggle::make('attribution_tracking_enabled')
                        ->label('UTM-tracking inschakelen')
                        ->helperText('Als dit uitstaat, slaat de middleware geen UTM-parameters meer op. Bestaande data op orders blijft bewaard.')
                        ->default(true),
                    Toggle::make('attribution_show_on_invoice')
                        ->label('Toon UTM-velden in factuur-PDF')
                        ->helperText('Voegt een klein blok met de bron-, medium- en campagne-waardes onderaan de factuur toe. Standaard uit, want klanten hoeven dit normaal niet te zien.')
                        ->default(false),
                ])
                ->columns(2),
            Section::make('Order opvolg flow')
                ->description('Globale fallback voor de :reviewUrl: variabele in de order-opvolg-mails. Wordt alleen gebruikt wanneer een flow geen eigen review-URLs heeft staan.')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('order_handled_flow_review_url')
                        ->label('Standaard review-URL')
                        ->helperText('Wordt als :reviewUrl: in opvolg-mails ingevuld wanneer de flow zelf geen review-URLs heeft staan. Vul per flow meerdere URLs in om A/B-testen tussen platformen mogelijk te maken.')
                        ->url()
                        ->maxLength(2048)
                        ->columnSpanFull(),
                ])
                ->columns(1),
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
            $channels = NotificationChannels::all();
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
                TagsInput::make("notification_bcc_order_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om alle bestel notificaties van de klant naar te sturen in BCC')
                    ->placeholder('Voer een email in')
                    ->reactive(),
                Section::make('Admin notificaties per bestel kanaal')
                    ->columnSpanFull()
                    ->schema(
                        collect(OrderOrigins::all($site['id']))
                            ->map(fn ($origin) => Section::make($origin['label'])
                                ->schema(
                                    collect($channels)
                                        ->map(fn ($channel) => Toggle::make("admin_notify_origin_{$site['id']}_{$origin['key']}_{$channel['key']}")
                                            ->label($channel['label'])
                                            ->inline(false))
                                        ->all()
                                )
                                ->columns(max(1, count($channels))))
                            ->all()
                    )
                    ->visible(count($channels) > 0)
                    ->collapsible()
                    ->collapsed(),
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

        $tabs = [];
        foreach ($sites as $site) {
            $tabs[] = Tab::make("suggestions-{$site['id']}")
                ->label('Suggesties - '.ucfirst($site['name']))
                ->schema([
                    Toggle::make("cart_suggestions_enabled_{$site['id']}")
                        ->label('Cart-suggesties aan')
                        ->helperText('Master kill-switch voor alle voorgestelde producten in cart, checkout en popup.')
                        ->columnSpanFull(),
                    TextInput::make("cart_suggestions_limit_cart_{$site['id']}")
                        ->label('Aantal kaarten - cart-pagina')
                        ->numeric()->minValue(1)->maxValue(20)->default(6),
                    TextInput::make("cart_suggestions_limit_checkout_{$site['id']}")
                        ->label('Aantal kaarten - checkout')
                        ->numeric()->minValue(1)->maxValue(20)->default(4),
                    TextInput::make("cart_suggestions_limit_popup_{$site['id']}")
                        ->label('Aantal kaarten - cart popup')
                        ->numeric()->minValue(1)->maxValue(10)->default(3),
                    TextInput::make("cart_suggestions_boost_slots_{$site['id']}")
                        ->label('Gegarandeerde slots voor gap-closers')
                        ->helperText('Aantal posities dat gereserveerd is voor producten die gratis verzending overbruggen.')
                        ->numeric()->minValue(0)->maxValue(10)->default(3),
                    TextInput::make("cart_suggestions_gap_min_factor_{$site['id']}")
                        ->label('Gap-factor min')
                        ->helperText('Sweet-spot ondergrens als factor van het gap (default 0.8 = 80%).')
                        ->numeric()->step(0.1)->default(0.8),
                    TextInput::make("cart_suggestions_gap_max_factor_{$site['id']}")
                        ->label('Gap-factor max')
                        ->helperText('Sweet-spot bovengrens als factor van het gap (default 1.5 = 150%).')
                        ->numeric()->step(0.1)->default(1.5),
                    Toggle::make("cart_suggestions_require_in_stock_{$site['id']}")
                        ->label('Alleen producten met voorraad'),
                    Toggle::make("cart_suggestions_fallback_random_{$site['id']}")
                        ->label('Random fallback aan')
                        ->helperText('Vult op met willekeurige producten als cross-sell + categorie-match niet genoeg geeft.'),
                ])
                ->columns(['default' => 1, 'lg' => 2]);
        }
        $tabGroups[] = Tabs::make('Suggesties')
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

            $emails = $this->form->getState()["notification_bcc_order_emails_{$site['id']}"];
            foreach ($emails ?? [] as $key => $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_bcc_order_emails', $emails, $site['id']);
            $formState["notification_bcc_order_emails_{$site['id']}"] = $emails;

            $overrides = [];
            $channels = NotificationChannels::all();
            foreach (OrderOrigins::all($site['id']) as $origin) {
                $channelValues = [];
                foreach ($channels as $channel) {
                    $formKey = "admin_notify_origin_{$site['id']}_{$origin['key']}_{$channel['key']}";
                    $channelValues[$channel['key']] = (bool) ($this->form->getState()[$formKey] ?? false);
                }
                $overrides[$origin['key']] = $channelValues;
            }
            Customsetting::set('admin_notify_per_order_origin', $overrides, $site['id']);

            Customsetting::set('cart_suggestions_enabled', $this->form->getState()["cart_suggestions_enabled_{$site['id']}"] ? '1' : '0', $site['id']);
            Customsetting::set('cart_suggestions_limit_cart', (string) ($this->form->getState()["cart_suggestions_limit_cart_{$site['id']}"] ?? 6), $site['id']);
            Customsetting::set('cart_suggestions_limit_checkout', (string) ($this->form->getState()["cart_suggestions_limit_checkout_{$site['id']}"] ?? 4), $site['id']);
            Customsetting::set('cart_suggestions_limit_popup', (string) ($this->form->getState()["cart_suggestions_limit_popup_{$site['id']}"] ?? 3), $site['id']);
            Customsetting::set('cart_suggestions_boost_slots', (string) ($this->form->getState()["cart_suggestions_boost_slots_{$site['id']}"] ?? 3), $site['id']);
            Customsetting::set('cart_suggestions_gap_min_factor', (string) ($this->form->getState()["cart_suggestions_gap_min_factor_{$site['id']}"] ?? 0.8), $site['id']);
            Customsetting::set('cart_suggestions_gap_max_factor', (string) ($this->form->getState()["cart_suggestions_gap_max_factor_{$site['id']}"] ?? 1.5), $site['id']);
            Customsetting::set('cart_suggestions_require_in_stock', $this->form->getState()["cart_suggestions_require_in_stock_{$site['id']}"] ? '1' : '0', $site['id']);
            Customsetting::set('cart_suggestions_fallback_random', $this->form->getState()["cart_suggestions_fallback_random_{$site['id']}"] ? '1' : '0', $site['id']);
        }

        Customsetting::set('apis', $this->form->getState()["apis"] ?? []);
        Customsetting::set('attribution_tracking_enabled', (bool) ($this->form->getState()['attribution_tracking_enabled'] ?? true));
        Customsetting::set('attribution_show_on_invoice', (bool) ($this->form->getState()['attribution_show_on_invoice'] ?? false));
        Customsetting::set('order_handled_flow_review_url', (string) ($this->form->getState()['order_handled_flow_review_url'] ?? ''));

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backfillApiSubscriptions')
                ->label('Bestaande e-mails synchroniseren')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(count(forms()->builder('orderApiClasses')) > 0)
                ->modalHeading('Bestaande e-mails synchroniseren naar APIs')
                ->modalDescription('Stuurt eerder verzamelde e-mailadressen alsnog naar de geselecteerde APIs. Reeds gesynchroniseerde combinaties van e-mail + API worden overgeslagen op basis van het log.')
                ->modalSubmitActionLabel('Backfill starten')
                ->form(function () {
                    $apiOptions = [];
                    foreach (Customsetting::get('apis', null, []) ?? [] as $configuredApi) {
                        if (! is_array($configuredApi) || empty($configuredApi['class'])) {
                            continue;
                        }
                        $class = $configuredApi['class'];
                        $label = $class;
                        if (class_exists($class)) {
                            $registry = collect(forms()->builder('orderApiClasses'));
                            $match = $registry->firstWhere('class', $class);
                            if ($match && ! empty($match['name'])) {
                                $label = $match['name'];
                                if (! empty($configuredApi['list_id'])) {
                                    $label .= ' (lijst: ' . $configuredApi['list_id'] . ')';
                                }
                            } else {
                                $label = class_basename($class);
                            }
                        }
                        $apiOptions[$class] = $label;
                    }

                    $sourceOptions = [];
                    if (DBSchema::hasTable('dashed__orders') && DBSchema::hasColumn('dashed__orders', 'email')) {
                        $sourceOptions['orders'] = 'Bestellingen (dashed__orders)';
                    }
                    if (DBSchema::hasTable('dashed__carts')) {
                        if (DBSchema::hasColumn('dashed__carts', 'email') || DBSchema::hasColumn('dashed__carts', 'abandoned_email')) {
                            $sourceOptions['carts'] = 'Winkelwagens (dashed__carts)';
                        }
                    }
                    if (DBSchema::hasTable('dashed__popup_views') && DBSchema::hasColumn('dashed__popup_views', 'email')) {
                        $sourceOptions['popup_views'] = 'Popup-inzendingen (dashed__popup_views)';
                    }
                    if (DBSchema::hasTable('dashed__form_input_fields') && DBSchema::hasTable('dashed__form_fields')) {
                        $sourceOptions['form_inputs'] = 'Formulier-inzendingen (dashed__form_inputs)';
                    }
                    if (DBSchema::hasTable('users') && DBSchema::hasColumn('users', 'email')) {
                        $sourceOptions['users'] = 'Klantaccounts (users)';
                    }

                    $orderOriginOptions = [];
                    if (\Illuminate\Support\Facades\Schema::hasColumn('dashed__orders', 'order_origin')) {
                        $orderOriginOptions = \Illuminate\Support\Facades\DB::table('dashed__orders')
                            ->select('order_origin')
                            ->whereNotNull('order_origin')
                            ->where('order_origin', '!=', '')
                            ->groupBy('order_origin')
                            ->orderBy('order_origin')
                            ->pluck('order_origin', 'order_origin')
                            ->toArray();
                    }
                    $defaultOrigins = array_values(array_diff(array_keys($orderOriginOptions), ['Bol']));

                    return [
                        CheckboxList::make('api_classes')
                            ->label('APIs')
                            ->helperText('Kies de APIs waar e-mails naartoe gestuurd moeten worden.')
                            ->options($apiOptions)
                            ->default(array_keys($apiOptions))
                            ->columns(1)
                            ->required(),
                        CheckboxList::make('sources')
                            ->label('Bronnen')
                            ->helperText('Kies waar de e-mailadressen vandaan moeten komen.')
                            ->options($sourceOptions)
                            ->default(array_keys($sourceOptions))
                            ->columns(1)
                            ->required(),
                        CheckboxList::make('order_origins')
                            ->label('Order-origins (alleen voor bron "orders")')
                            ->helperText('Kies welke order-origins meegenomen worden uit de orders-bron. Bol-bestellingen staan standaard uit.')
                            ->options($orderOriginOptions)
                            ->default($defaultOrigins)
                            ->columns(1)
                            ->visible(! empty($orderOriginOptions)),
                        Toggle::make('only_marketing')
                            ->label('Alleen waar marketing-toestemming aanwezig is')
                            ->helperText('Filtert bestellingen + klantaccounts op marketing = true. Bronnen zonder marketing-kolom worden volledig meegenomen.')
                            ->default(false),
                        TextInput::make('batch_size')
                            ->label('Batchgrootte')
                            ->helperText('Aantal records per batch om rate-limits van externe APIs te respecteren.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(500)
                            ->default(50)
                            ->required(),
                    ];
                })
                ->action(function (array $data): void {
                    BackfillApiSubscriptionsJob::dispatch(
                        apiClasses: array_values($data['api_classes'] ?? []),
                        sources: array_values($data['sources'] ?? []),
                        onlyMarketing: (bool) ($data['only_marketing'] ?? false),
                        batchSize: (int) ($data['batch_size'] ?? 50),
                        orderOrigins: array_values($data['order_origins'] ?? []),
                    );

                    Notification::make()
                        ->title('Backfill gestart - resultaten verschijnen in het log')
                        ->success()
                        ->send();
                }),
        ];
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
