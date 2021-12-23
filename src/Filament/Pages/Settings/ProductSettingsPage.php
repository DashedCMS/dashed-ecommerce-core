<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Card;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;

class ProductSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Product instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Product instellingen';

    protected static string $view = 'qcommerce-core::settings.pages.default-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["add_to_cart_redirect_to_{$site['id']}"] = Customsetting::get('add_to_cart_redirect_to', $site['id'], 'same');
            $formData["product_filter_option_order_by_{$site['id']}"] = Customsetting::get('product_filter_option_order_by', $site['id'], 'order');
            $formData["product_out_of_stock_sellable_date_should_be_valid_{$site['id']}"] = Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', $site['id'], 1);
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
                    ->label("Product instellingen voor {$site['name']}"),
                Select::make("add_to_cart_redirect_to_{$site['id']}")
                    ->options([
                        'same' => 'Zelfde pagina',
                        'cart' => 'Winkelwagen (Hier bekijk je je mandje)',
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
                        '0' => 'Nee'
                    ])
                    ->label('Indien een product doorverkocht kan worden bij 0 voorraad, moet de \'weer op voorraad\' datum dan in de toekomst liggen?')
                    ->required(),
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
            Customsetting::set('add_to_cart_redirect_to', $this->form->getState()["add_to_cart_redirect_to_{$site['id']}"], $site['id']);
            Customsetting::set('product_filter_option_order_by', $this->form->getState()["product_filter_option_order_by_{$site['id']}"], $site['id']);
            Customsetting::set('product_out_of_stock_sellable_date_should_be_valid', $this->form->getState()["product_out_of_stock_sellable_date_should_be_valid_{$site['id']}"], $site['id']);
        }

        $this->notify('success', 'De product instellingen zijn opgeslagen');
    }
}
