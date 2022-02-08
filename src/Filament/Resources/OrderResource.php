<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Illuminate\Support\Str;
use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Qubiqx\QcommerceCore\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\BadgeColumn;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Classes\Orders;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\EditOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\ListOrders;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\CancelOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\CreateOrder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-cash';
    protected static ?string $navigationGroup = 'E-commerce';

    protected static function getNavigationLabel(): string
    {
        return 'Bestellingen (' . Order::unhandled()->count() . ')';
    }

    protected static ?string $label = 'Bestelling';
    protected static ?string $pluralLabel = 'Bestellingen';
    protected static ?int $navigationSort = 0;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "$record->invoice_id - $record->name";
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return route('filament.resources.orders.view', ['record' => $record]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'hash',
            'id',
            'ip',
            'first_name',
            'last_name',
            'email',
            'street',
            'house_nr',
            'zip_code',
            'city',
            'country',
            'company_name',
            'btw_id',
            'note',
            'invoice_first_name',
            'invoice_last_name',
            'invoice_street',
            'invoice_house_nr',
            'invoice_zip_code',
            'invoice_city',
            'invoice_country',
            'invoice_id',
            'total',
            'subtotal',
            'btw',
            'discount',
            'status',
            'site_id',
        ];
    }

    public static function form(Form $form): Form
    {
        $schema = [];

//        $schema[] = Section::make('Persoonlijke informatie')
//            ->schema([
//                Select::make('user_id')
//                    ->label('Hang de bestelling aan een gebruiker')
//                    ->searchable()
//                    ->options(array_merge([
//                        '' => 'Geen gebruiker'
//                    ], User::all()->pluck('name', 'id')->toArray()))
//                    ->reactive(),
//                Toggle::make('marketing')
//                    ->label('De klant accepteer marketing'),
//                TextInput::make('password')
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255',
//                        'confirmed'
//                    ])
//                    ->visible(fn(\Closure $get) => !$get('user_id')),
//                TextInput::make('password_confirmation')
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ])
//                    ->visible(fn(\Closure $get) => !$get('user_id')),
//                TextInput::make('first_name')
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('last_name')
//                    ->required()
//                    ->rules([
//                        'required',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('email')
//                    ->type('email')
//                    ->required()
//                    ->rules([
//                        'required',
//                        'email:rfc',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('phone_number')
//                    ->rules([
//                        'max:255'
//                    ]),
//                TextInput::make('street')
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ])
//                    ->reactive(),
//                TextInput::make('house_nr')
//                    ->required(fn(\Closure $get) => $get('street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('zip_code')
//                    ->required(fn(\Closure $get) => $get('street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('city')
//                    ->required(fn(\Closure $get) => $get('street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('country')
//                    ->required()
//                    ->rules([
//                        'required',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('company_name')
//                    ->rules([
//                        'max:255'
//                    ]),
//                TextInput::make('btw_id')
//                    ->rules([
//                        'max:255'
//                    ]),
//                TextInput::make('invoice_street')
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ])
//                    ->reactive(),
//                TextInput::make('invoice_house_nr')
//                    ->required(fn(\Closure $get) => $get('invoice_street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('invoice_zip_code')
//                    ->required(fn(\Closure $get) => $get('invoice_street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('invoice_city')
//                    ->required(fn(\Closure $get) => $get('invoice_street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                TextInput::make('invoice_country')
//                    ->required(fn(\Closure $get) => $get('invoice_street'))
//                    ->rules([
//                        'nullable',
//                        'min:6',
//                        'max:255'
//                    ]),
//                Textarea::make('note')
//                    ->label('Notitie')
//                    ->rules([
//                        'nullable',
//                        'max:1500'
//                    ]),
//                TextInput::make('discount_code')
//                    ->label('Kortingscode ')
//                    ->rules([
//                        'nullable',
//                        'max:255'
//                    ]),
//                Repeater::make('orderProducts')
//                    ->label('Producten')
//                    ->schema([
//
//                    ])
//                    ->columnSpan([
//                        'default' => 1,
//                        'lg' => 2,
//                    ])
//            ])
//            ->columns([
//                'default' => 1,
//                'lg' => 2,
//            ]);

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_id')
//                    ->getStateUsing('')
                    ->label('Bestelling ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Betaalmethode')
                    ->getStateUsing(fn ($record) => Str::substr($record->payment_method, 0, 10)),
                BadgeColumn::make('payment_status')
                    ->label('Betaalstatus')
                    ->getStateUsing(fn ($record) => $record->orderStatus()['status'])
                    ->colors([
                        'primary' => fn ($state): bool => $state === 'Lopende aankoop',
                        'danger' => fn ($state): bool => $state === 'Geannuleerd',
                        'warning' => fn ($state): bool => in_array($state, ['Gedeeltelijk betaald', 'Retour']),
                        'success' => fn ($state): bool => in_array($state, ['Betaald', 'Wachten op bevestiging betaling']),
                    ]),
                BadgeColumn::make('fulfillment_status')
                    ->label('Fulfillment status')
                    ->getStateUsing(fn ($record) => Orders::getFulfillmentStatusses()[$record->fulfillment_status] ?? '')
                    ->colors([
                        'danger',
                        'success' => fn ($state): bool => ($state === 'Afgehandeld' || $state === 'Verzonden'),
                    ]),
//                ViewColumn::make('statusLabels')
//            ->view('filament.tables.columns.multiple-labels')
//                    ->hidden(Customsetting::get('order_index_show_other_statuses', Sites::getActive(), true) ? false : true),
//                TagsColumn::make('statusLabels')
//                    ->label('Andere statussen')
//                    ->getStateUsing(fn($record) => $record->statusLabels)
//                    ->hidden(Customsetting::get('order_index_show_other_statuses', Sites::getActive(), true) ? false : true),
                TextColumn::make('name')
                    ->label('Klant')
                    ->searchable([
                        'hash',
                        'id',
                        'ip',
                        'first_name',
                        'last_name',
                        'email',
                        'street',
                        'house_nr',
                        'zip_code',
                        'city',
                        'country',
                        'company_name',
                        'btw_id',
                        'note',
                        'invoice_first_name',
                        'invoice_last_name',
                        'invoice_street',
                        'invoice_house_nr',
                        'invoice_zip_code',
                        'invoice_city',
                        'invoice_country',
                        'invoice_id',
                        'total',
                        'subtotal',
                        'btw',
                        'discount',
                        'status',
                        'site_id',
                    ])
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Totaal')
                    ->getStateUsing(fn ($record) => CurrencyHelper::formatPrice($record->total)),
                TextColumn::make('created_at')
                    ->label('Aangemaakt op')
                    ->getStateUsing(fn ($record) => $record->created_at->format('d-m-Y H:i'))
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
//            'edit' => EditOrder::route('/{record}/edit'),
            'view' => ViewOrder::route('/{record}/view'),
            'cancel' => CancelOrder::route('/{record}/cancel'),
        ];
    }
}
