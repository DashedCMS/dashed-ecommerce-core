<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Forms\Components\Tabs;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;

class ProductSettingsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Product instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Product instellingen';

    protected static string $view = 'dashed-core::settings.pages.default-settings';
    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["add_to_cart_redirect_to_{$site['id']}"] = Customsetting::get('add_to_cart_redirect_to', $site['id'], 'same');
            $formData["product_filter_option_order_by_{$site['id']}"] = Customsetting::get('product_filter_option_order_by', $site['id'], 'order');
            $formData["product_out_of_stock_sellable_date_should_be_valid_{$site['id']}"] = Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', $site['id'], 1);
            $formData["product_default_order_type_{$site['id']}"] = Customsetting::get('product_default_order_type', $site['id'], 'price');
            $formData["product_default_order_sort_{$site['id']}"] = Customsetting::get('product_default_order_sort', $site['id'], 'DESC');
            $formData["product_default_amount_of_products_{$site['id']}"] = Customsetting::get('product_default_amount_of_products', $site['id'], 12);
            $formData["product_use_simple_variation_style_{$site['id']}"] = Customsetting::get('product_use_simple_variation_style', $site['id'], false);
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
                    ->label("Product instellingen voor {$site['name']}")
                    ->columnSpan(2),
                Select::make("add_to_cart_redirect_to_{$site['id']}")
                    ->options([
                        'same' => 'Zelfde pagina',
                        'cart' => 'Winkelwagen (Hier bekijk je je winkelmand)',
                        'checkout' => 'Checkout (Hier ga je afrekenen)',
                    ])
                    ->label('Waar moet de pagina naartoe gaan als je een item in je winkelmand toevoegd')
                    ->required(),
                Select::make("product_filter_option_order_by_{$site['id']}")
                    ->options([
                        'order' => 'Volgorde',
                        'name' => 'Naam',
                    ])
                    ->label('Op basis waarvan moeten product filter opties gesorteerd worden')
                    ->required(),
                Select::make("product_out_of_stock_sellable_date_should_be_valid_{$site['id']}")
                    ->options([
                        '1' => 'Ja',
                        '0' => 'Nee',
                    ])
                    ->label('Indien een product doorverkocht kan worden bij 0 voorraad, moet de \'weer op voorraad\' datum dan in de toekomst liggen?')
                    ->required(),
                Select::make("product_default_order_type_{$site['id']}")
                    ->options([
                        'price' => 'Prijs',
                        'purchases' => 'Aantal verkopen',
                        'stock' => 'Vooraad',
                        'created_at' => 'Aangemaakte op',
                        'order' => 'Volgorde van producten',
                    ])
                    ->label('Producten sorteren op')
                    ->required(),
                Select::make("product_default_order_sort_{$site['id']}")
                    ->options([
                        'DESC' => 'Aflopend',
                        'ASC' => 'Oplopend',
                    ])
                    ->label('Standaard sortering van producten')
                    ->required(),
                TextInput::make("product_default_amount_of_products_{$site['id']}")
                    ->label('Standaard aantal producten per pagina')
                    ->numeric()
                    ->required(),
                Toggle::make("product_use_simple_variation_style_{$site['id']}")
                    ->label('Gebruik product variaties op de simpele manier')
                    ->helperText('Dit refreshed niet meer de pagina, maar veranderd de prijs direct'),
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
            Customsetting::set('add_to_cart_redirect_to', $this->form->getState()["add_to_cart_redirect_to_{$site['id']}"], $site['id']);
            Customsetting::set('product_filter_option_order_by', $this->form->getState()["product_filter_option_order_by_{$site['id']}"], $site['id']);
            Customsetting::set('product_out_of_stock_sellable_date_should_be_valid', $this->form->getState()["product_out_of_stock_sellable_date_should_be_valid_{$site['id']}"], $site['id']);
            Customsetting::set('product_default_order_type', $this->form->getState()["product_default_order_type_{$site['id']}"], $site['id']);
            Customsetting::set('product_default_order_sort', $this->form->getState()["product_default_order_sort_{$site['id']}"], $site['id']);
            Customsetting::set('product_default_amount_of_products', $this->form->getState()["product_default_amount_of_products_{$site['id']}"], $site['id']);
            Customsetting::set('product_use_simple_variation_style', $this->form->getState()["product_use_simple_variation_style_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De product instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
