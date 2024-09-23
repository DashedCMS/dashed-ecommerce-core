<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Carbon\Carbon;
use Filament\Forms\Get;
use Filament\Actions\Action;
use Dashed\DashedCore\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Wizard;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Components\DateTimePicker;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class CreateOrder extends Page
{
    protected static string $resource = OrderResource::class;
    protected static ?string $title = 'Bestelling aanmaken';
    protected static string $view = 'dashed-ecommerce-core::orders.create-order';

    use OrderResource\Concerns\CreateManualOrderActions;

    public function mount(): void
    {
        $this->initialize('handorder');
    }

    protected function getActions(): array
    {
        return [
            Action::make('updateInfo')
                ->label('Gegevens bijwerken')
                ->action(fn() => $this->updateInfo()),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = [];

        $schema[] = Wizard\Step::make('Persoonlijke informatie')
            ->schema([
                Select::make('user_id')
                    ->label('Hang de bestelling aan een gebruiker')
                    ->options($this->users)
                    ->searchable()
                    ->reactive(),
                Toggle::make('marketing')
                    ->label('De klant accepteert marketing'),
                TextInput::make('password')
                    ->label('Wachtwoord')
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn(Get $get) => !$get('user_id')),
                TextInput::make('password_confirmation')
                    ->label('Wachtwoord herhalen')
                    ->type('password')
                    ->nullable()
                    ->minLength(6)
                    ->maxLength(255)
                    ->confirmed()
                    ->visible(fn(Get $get) => !$get('user_id')),
                TextInput::make('first_name')
                    ->label('Voornaam')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Achternaam')
                    ->required()
                    ->nullable()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->label('Geboortedatum')
                    ->nullable()
                    ->date(),
                Select::make('gender')
                    ->label('Geslacht')
                    ->options([
                        '' => 'Niet gekozen',
                        'm' => 'Man',
                        'f' => 'Vrouw',
                    ]),
                TextInput::make('email')
                    ->label('Email')
                    ->type('email')
                    ->required()
                    ->email()
                    ->minLength(4)
                    ->maxLength(255),
                TextInput::make('phone_number')
                    ->label('Telefoon nummer')
                    ->maxLength(255),
            ])
            ->columns(2);

        $schema[] = Wizard\Step::make('Adres')
            ->schema([
                TextInput::make('street')
                    ->label('Straat')
                    ->nullable()
                    ->maxLength(255)
                    ->lazy()
                    ->reactive(),
                TextInput::make('house_nr')
                    ->label('Huisnummer')
                    ->nullable()
                    ->required(fn(Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('zip_code')
                    ->label('Postcode')
                    ->required(fn(Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn(Get $get) => $get('street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('country')
                    ->label('Land')
                    ->required()
                    ->nullable()
                    ->maxLength(255)
                    ->lazy(),
                TextInput::make('company_name')
                    ->label('Bedrijfsnaam')
                    ->maxLength(255),
                TextInput::make('btw_id')
                    ->label('BTW id')
                    ->maxLength(255),
                TextInput::make('invoice_street')
                    ->label('Factuur straat')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('invoice_house_nr')
                    ->label('Factuur huisnummer')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_zip_code')
                    ->label('Factuur postcode')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_city')
                    ->label('Factuur stad')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('invoice_country')
                    ->label('Factuur land')
                    ->required(fn(Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->maxLength(255),
            ])
            ->columns(2);

        $schema[] = Wizard\Step::make('Producten')
            ->schema([
                Repeater::make('products')
                    ->label('Kies producten')
                    ->helperText('Check goed of de producten op voorraad zijn. Als een product niet op voorraad is, wordt hij bij stap 5 wel meegeteld, maar niet aangemaakt in de bestelling.')
                    ->schema([
                        Select::make('id')
                            ->label('Kies product')
                            ->options($this->allProducts->pluck('name', 'id'))
                            ->searchable()
                            ->reactive(),
                        TextInput::make('quantity')
                            ->label('Aantal')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0),
                        Placeholder::make('Voorraad')
                            ->content(fn(Get $get) => $get('id') ? Product::find($get('id'))->total_stock : 'Kies een product'),
                        Placeholder::make('Prijs')
                            ->content(fn(Get $get) => $get('id') ? Product::find($get('id'))->currentPrice : 'Kies een product'),
                        Placeholder::make('Afbeelding')
                            ->content(fn(Get $get) => $get('id') ? new HtmlString('<img width="300" src="' . (mediaHelper()->getSingleImage(Product::find($get('id'))->firstImage, 'medium')->url ?? '') . '">') : 'Kies een product'),
                        Section::make('Extra\'s')
                            ->schema(fn(Get $get) => $get('id') ? $this->getProductExtrasSchema(Product::find($get('id'))) : []),
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

        //            $productSchemas[] = Section::make('Product ' . $product->name)
        //                ->schema(array_merge([
        //                    TextInput::make('products.' . $product->id . '.quantity')
        //                        ->label('Aantal')
        //                        ->numeric()
        //                        ->required()
        //                        ->minValue(0)
        //                        ->maxValue(1000)
        //                        ->default(0),
        //                    Placeholder::make('Voorraad')
        //                        ->content($product->stock()),
        //                    Placeholder::make('Prijs')
        //                        ->content($product->currentPrice),
        //                    Placeholder::make('Afbeelding')
        //                        ->content(new HtmlString('<img width="300" src="' . app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $product->firstImage, []) . '">')),
        //                ], $productExtras))
        //                ->visible(fn(Get $get) => in_array($product->id, $get('activatedProducts')));
        //        }
        //
        //        $schema[] = Wizard\Step::make('Producten')
        //            ->schema($productSchemas)
        //            ->columnSpan(2);

        $schema[] = Wizard\Step::make('Overige informatie')
            ->schema([
                Textarea::make('note')
                    ->label('Notitie')
                    ->nullable()
                    ->maxLength(1500),
                TextInput::make('discount_code')
                    ->label('Kortingscode')
                    ->nullable()
                    ->maxLength(255)
                    ->reactive(),
                Select::make('shipping_method_id')
                    ->label('Verzendmethode')
                    ->required()
                    ->options(function () {
                        return collect(ShoppingCart::getAvailableShippingMethods($this->country, true))->pluck('name', 'id')->toArray();
                    }),
            ]);

        $schema[] = Wizard\Step::make('Bestelling')
            ->schema([
                Placeholder::make('')
                    ->content('Subtotaal: ' . $this->subTotal),
                Placeholder::make('')
                    ->content('Korting: ' . $this->discount),
                Placeholder::make('')
                    ->content('BTW: ' . $this->vat),
                Placeholder::make('')
                    ->content('Totaal: ' . $this->total),
            ]);

        return [
            Wizard::make($schema)
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
