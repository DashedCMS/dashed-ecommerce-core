<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class CreateOrder extends Page implements HasSchemas
{
    use OrderResource\Concerns\CreateManualOrderActions;
    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Bestelling aanmaken';
    protected string $view = 'dashed-ecommerce-core::orders.create-order';

    public ?array $data = [];

    public function mount(): void
    {
        $this->initialize('handorder', 'own');
    }

    protected function getActions(): array
    {
        return [
            Action::make('updateInfo')
                ->label(__('Gegevens bijwerken'))
                ->action(fn () => $this->updateInfo()),
        ];
    }

    public function createOrderForm(Schema $schema): Schema
    {
        $newSchema = [];

        $newSchema[] = Wizard\Step::make('Persoonlijke informatie')
            ->schema([
                Select::make('user_id')
                    ->label(__('Hang de bestelling aan een gebruiker'))
                    ->options($this->users)
                    ->searchable()
                    ->reactive(),
                Toggle::make('marketing')
                    ->label(__('De klant accepteert marketing')),
                TextInput::make('password')
                    ->label(__('Wachtwoord'))
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn (Get $get) => ! $get('user_id')),
                TextInput::make('password_confirmation')
                    ->label(__('Wachtwoord herhalen'))
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn (Get $get) => ! $get('user_id')),
                TextInput::make('first_name')
                    ->label(__('Voornaam'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label(__('Achternaam'))
                    ->required()
                    ->nullable()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label(__('Geboortedatum'))
                    ->nullable()
                    ->date(),
                Select::make('gender')
                    ->label(__('Geslacht'))
                    ->options([
                        '' => __('Niet gekozen'),
                        'm' => __('Man'),
                        'f' => __('Vrouw'),
                    ]),
                TextInput::make('email')
                    ->label(__('Email'))
                    ->type('email')
                    ->required()
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),
                TextInput::make('phone_number')
                    ->label(__('Telefoon nummer'))
                    ->maxLength(255),
            ])
            ->columns(2);

        $newSchema[] = Wizard\Step::make('Adres')
            ->schema([
                TextInput::make('street')
                    ->label(__('Straat'))
                    ->nullable()
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),
                TextInput::make('house_nr')
                    ->label(__('Huisnummer'))
                    ->nullable()
                    ->required(fn (Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('zip_code')
                    ->label(__('Postcode'))
                    ->required(fn (Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label(__('Stad'))
                    ->required(fn (Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                Select::make('country')
                    ->label(__('Land'))
                    ->options(function () {
                        $countries = Countries::getAllSelectedCountries();
                        $options = [];
                        foreach ($countries as $country) {
                            $options[$country] = $country;
                        }

                        return $options;
                    })
                    ->required()
                    ->nullable()
                    ->lazy(),
                TextInput::make('company_name')
                    ->label(__('Bedrijfsnaam'))
                    ->maxLength(255),
                TextInput::make('btw_id')
                    ->label(__('BTW id'))
                    ->maxLength(255),
                TextInput::make('invoice_street')
                    ->label(__('Factuur straat'))
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('invoice_house_nr')
                    ->label(__('Factuur huisnummer'))
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_zip_code')
                    ->label(__('Factuur postcode'))
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_city')
                    ->label(__('Factuur stad'))
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                Select::make('invoice_country')
                    ->label(__('Factuur land'))
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->options(function () {
                        $countries = Countries::getAllSelectedCountries();
                        $options = [];
                        foreach ($countries as $country) {
                            $options[$country] = $country;
                        }

                        return $options;
                    })
                    ->nullable(),
            ])
            ->columns(2);

        $newSchema[] = Wizard\Step::make('Producten')
            ->schema([
                Repeater::make('products')
                    ->label(__('Kies producten'))
                    ->helperText(__('Check goed of de producten op voorraad zijn. Als een product niet op voorraad is, wordt hij bij stap 5 wel meegeteld, maar niet aangemaakt in de bestelling.'))
                    ->schema([
                        Select::make('id')
                            ->label(__('Kies product'))
                            ->options(Product::handOrderShowable()->pluck('name', 'id'))
//                            ->options($this->allProducts->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->reactive(),
                        TextInput::make('quantity')
                            ->label(__('Aantal'))
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0),
                        TextEntry::make('Voorraad')
                            ->label(fn (Get $get) => $get('id') ? Product::find($get('id'))->total_stock : __('Kies een product')),
                        TextEntry::make('Prijs')
                            ->label(fn (Get $get) => $get('id') ? Product::find($get('id'))->currentPrice : __('Kies een product')),
                        TextEntry::make('Afbeelding')
                            ->visible(fn (Get $get) => $get('id') && Product::find($get('id'))->firstImage)
                            ->label(fn (Get $get) => $get('id') ? new HtmlString('<img width="300" src="' . (mediaHelper()->getSingleMedia(Product::find($get('id'))->firstImage, 'original')->url ?? '') . '">') : __('Kies een product')),
                        Section::make(__('Extra\'s'))
                            ->columnSpanFull()
                            ->schema(fn (Get $get) => $get('id') ? $this->getProductExtrasSchema(Product::find($get('id'))) : []),
                    ]),
            ])
            ->columnSpan(2);

        //        $productSchemas = [];
        //
        //        $productSchemas[] = Select::make('activatedProducts')
        //            ->label('Kies producten')
        //            ->helperText('Check goed of de producten op voorraad zijn. Als een product niet op voorraad is, wordt hij bij stap 5 wel meegeteld, maar niet aangemaakt in de bestelling.')
        //            ->options(Product::handOrderShowable()->pluck('name', 'id'))
        //            ->searchable()
        //            ->multiple()
        //            ->reactive();
        //
        //        foreach ($this->getAllProductsProperty() as $product) {
        //            $productExtras = [];

        //            foreach ($product['productExtras'] as $extra) {
        //                $extraOptions = [];
        //                foreach ($extra['product_extra_options'] ?? [] as $option) {
        //                    $option = ProductExtraOption::find($option['id']);
        //                    $extraOptions[$option->id] = $option->value . ' (+ ' . CurrencyHelper::formatPrice($option->price) . ')';
        //                }
        //
        //                $productExtras[] = Select::make('products.' . $product->id . '.extra.' . $extra['id'])
        //                    ->label($extra['name'][array_key_first($extra['name'])])
        //                    ->options($extraOptions)
        //                    ->required($extra['required']);
        //            }

        //            $productSchemas[] = Section::make('Product ' . $product->name)->columnSpanFull()
        //                ->schema(array_merge([
        //                    TextInput::make('products.' . $product->id . '.quantity')
        //                        ->label('Aantal')
        //                        ->numeric()
        //                        ->required()
        //                        ->minValue(0)
        //                        ->maxValue(1000)
        //                        ->default(0),
        //                    TextEntry::make('Voorraad')
        //                        ->state($product->stock()),
        //                    TextEntry::make('Prijs')
        //                        ->state($product->currentPrice),
        //                    TextEntry::make('Afbeelding')
        //                        ->state(new HtmlString('<img width="300" src="' . app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $product->firstImage, []) . '">')),
        //                ], $productExtras))
        //                ->visible(fn(Get $get) => in_array($product->id, $get('activatedProducts')));
        //        }
        //
        //        $schema[] = Wizard\Step::make('Producten')
        //            ->schema($productSchemas)
        //            ->columnSpan(2);

        $newSchema[] = Wizard\Step::make('Overige informatie')
            ->schema([
                Textarea::make('note')
                    ->label(__('Notitie'))
                    ->nullable()
                    ->maxLength(1500),
                TextInput::make('discount_code')
                    ->label(__('Kortingscode'))
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                Select::make('shipping_method_id')
                    ->label(__('Verzendmethode'))
                    ->options(function () {
                        return collect(ShoppingCart::getAllShippingMethods($this->country, true))->pluck('correctName', 'id')->toArray();
                    }),
            ]);

        $newSchema[] = Wizard\Step::make('Bestelling')
            ->schema([
                TextEntry::make('subtotaal')
                    ->state('Subtotaal: ' . $this->subTotal),
                TextEntry::make('korting')
                    ->state('Korting: ' . $this->discount),
                TextEntry::make('btw')
                    ->state('BTW')
                    ->state('BTW: ' . $this->vat),
                TextEntry::make('totaal')
                    ->state('Totaal: ' . $this->total),
            ]);

        $newSchema = [
            Wizard::make($newSchema)
                ->submitAction(new HtmlString(Blade::render(
                    <<<BLADE
    <x-filament::button
        type="submit"
        size="sm"
    >
        Bestelling aanmaken
    </x-filament::button>
BLADE
                ))),
        ];

        return $schema
            ->fill([
                'country' => Countries::getAllSelectedCountries()[0],
            ])
            ->schema($newSchema);
    }

    public function submit()
    {
        $response = $this->createOrder();

        if ($response['success']) {
            $order = $response['order'];
            //        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $newPaymentStatus = 'waiting_for_confirmation';
            $order->changeStatus($newPaymentStatus);

            return redirect(url(route('filament.dashed.resources.orders.view', [$order])));
            //        } else {
            //            try {
            //                $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
            //            } catch (\Exception $exception) {
            //                throw new \Exception('Cannot start payment: ' . $exception->getMessage());
            //            }
            //
            //            return redirect($transaction['redirectUrl'], 303);
            //        }
        }
    }
}
