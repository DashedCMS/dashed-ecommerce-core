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
use Dashed\DashedPages\Models\Page as PageModel;

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
            $formData["products_hide_parents_in_overview_{$site['id']}"] = Customsetting::get('products_hide_parents_in_overview', $site['id'], false);
            $formData["product_redirect_after_new_variation_selected_{$site['id']}"] = Customsetting::get('product_redirect_after_new_variation_selected', $site['id'], false);
            $formData["product_overview_page_id_{$site['id']}"] = Customsetting::get('product_overview_page_id', $site['id']);
            $formData["cart_page_id_{$site['id']}"] = Customsetting::get('cart_page_id', $site['id']);
            $formData["checkout_page_id_{$site['id']}"] = Customsetting::get('checkout_page_id', $site['id']);
            $formData["orders_page_id_{$site['id']}"] = Customsetting::get('orders_page_id', $site['id']);
            $formData["order_page_id_{$site['id']}"] = Customsetting::get('order_page_id', $site['id']);
            $formData["product_category_index_page_enabled_{$site['id']}"] = Customsetting::get('product_category_index_page_enabled', $site['id'], true);
            $formData["fill_with_first_product_if_product_group_loaded_{$site['id']}"] = Customsetting::get('fill_with_first_product_if_product_group_loaded', $site['id'], true);
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
                Select::make("product_overview_page_id_{$site['id']}")
                    ->label('Product overview pagina')
                    ->options(PageModel::thisSite($site['id'])->pluck('name', 'id')),
                Select::make("cart_page_id_{$site['id']}")
                    ->label('Winkelwagen pagina')
                    ->options(PageModel::thisSite($site['id'])->pluck('name', 'id')),
                Select::make("checkout_page_id_{$site['id']}")
                    ->label('Afreken pagina')
                    ->options(PageModel::thisSite($site['id'])->pluck('name', 'id')),
                Select::make("orders_page_id_{$site['id']}")
                    ->label('Bestellingen pagina')
                    ->options(PageModel::thisSite($site['id'])->pluck('name', 'id')),
                Select::make("order_page_id_{$site['id']}")
                    ->label('Bestelling pagina')
                    ->options(PageModel::thisSite($site['id'])->pluck('name', 'id')),
                Toggle::make("product_use_simple_variation_style_{$site['id']}")
                    ->label('Gebruik product variaties op de Livewire manier')
                    ->helperText('Alleen gebruiken als jouw webshop hiervoor gebouwd is'),
//                Toggle::make("products_hide_parents_in_overview_{$site['id']}")
//                    ->label('Verberg alle hoofdproducten'),
                Toggle::make("product_redirect_after_new_variation_selected_{$site['id']}")
                    ->label('Redirect naar nieuwe pagina als nieuwe variatie gevonden is'),
                Toggle::make("product_category_index_page_enabled_{$site['id']}")
                    ->label('Product categorie index pagina inschakelen'),
                Toggle::make("fill_with_first_product_if_product_group_loaded_{$site['id']}")
                    ->label('Vul product groep met eerste product als deze geladen wordt'),
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
            //            Customsetting::set('products_hide_parents_in_overview', $this->form->getState()["products_hide_parents_in_overview_{$site['id']}"], $site['id']);
            Customsetting::set('product_redirect_after_new_variation_selected', $this->form->getState()["product_redirect_after_new_variation_selected_{$site['id']}"], $site['id']);
            Customsetting::set('product_overview_page_id', $this->form->getState()["product_overview_page_id_{$site['id']}"], $site['id']);
            Customsetting::set('cart_page_id', $this->form->getState()["cart_page_id_{$site['id']}"], $site['id']);
            Customsetting::set('checkout_page_id', $this->form->getState()["checkout_page_id_{$site['id']}"], $site['id']);
            Customsetting::set('orders_page_id', $this->form->getState()["orders_page_id_{$site['id']}"], $site['id']);
            Customsetting::set('order_page_id', $this->form->getState()["order_page_id_{$site['id']}"], $site['id']);
            Customsetting::set('product_category_index_page_enabled', $this->form->getState()["product_category_index_page_enabled_{$site['id']}"], $site['id']);
            Customsetting::set('fill_with_first_product_if_product_group_loaded', $this->form->getState()["fill_with_first_product_if_product_group_loaded_{$site['id']}"], $site['id']);
        }

        Notification::make()
            ->title('De product instellingen zijn opgeslagen')
            ->success()
            ->send();
    }
}
