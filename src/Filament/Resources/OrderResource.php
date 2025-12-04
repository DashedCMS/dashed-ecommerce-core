<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\EditOrder;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ViewOrder;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ListOrders;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\CreateOrder;

class OrderResource extends Resource
{
    use WithFileUploads;
    protected static ?string $model = Order::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';

    public static function getNavigationLabel(): string
    {
        return 'Bestellingen';
    }

    public static function getNavigationBadge(): ?string
    {
        return Order::unhandled()->count();
    }

    protected static ?string $label = 'Bestelling';
    protected static ?string $pluralLabel = 'Bestellingen';
    protected static ?int $navigationSort = 0;

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "$record->invoice_id - $record->name";
    }

    public static function getGlobalSearchEloquentQuery(): EloquentBuilder
    {
        return parent::getGlobalSearchEloquentQuery()->isPaid();
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return route('filament.dashed.resources.orders.view', ['record' => $record]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return array_merge([
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
        ], collect(ecommerce()->builder('customOrderFields'))
            ->keys()
            ->map(fn ($key) => Str::snake($key))
            ->toArray());
    }

    public static function form(Schema $schema): Schema
    {
        $newSchema = [];

        $newSchema[] = Section::make('Persoonlijke informatie')->columnSpanFull()
            ->schema([
                TextInput::make('first_name')
                    ->label('Voornaam')
                    ->nullable()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Achternaam')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->required()
                    ->maxLength(255)
                    ->email(),
                TextInput::make('phone_number')
                    ->label('Telefoonnummer')
                    ->maxLength(255),
                TextInput::make('street')
                    ->label('Straat')
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('house_nr')
                    ->label('Huisnummer')
                    ->required(fn (Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('zip_code')
                    ->label('Postcode')
                    ->required(fn (Get $get) => $get('street'))
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Stad')
                    ->required(fn (Get $get) => $get('street'))
                    ->maxLength(255),
                Select::make('country')
                    ->label('Land')
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
                Textarea::make('note')
                    ->label('Notitie')
                    ->nullable()
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ])
            ->hiddenOn(ViewOrder::class)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema[] = Section::make('Bedrijfsinformatie')->columnSpanFull()
            ->schema([
                TextInput::make('company_name')
                ->label('Bedrijfsnaam')
                ->maxLength(255),
                TextInput::make('btw_id')
                    ->label('Btw ID')
                    ->maxLength(255),
            ])
            ->hiddenOn(ViewOrder::class)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $newSchema[] = Section::make('Factuur informatie')->columnSpanFull()
            ->schema([
                TextInput::make('invoice_street')
                    ->label('Straat')
                    ->maxLength(255)
                    ->reactive(),
                TextInput::make('invoice_house_nr')
                    ->label('Huisnummer')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->maxLength(255),
                TextInput::make('invoice_zip_code')
                    ->label('Postcode')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->maxLength(255),
                TextInput::make('invoice_city')
                    ->label('Stad')
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->maxLength(255),
                Select::make('invoice_country')
                    ->label('Land')
                    ->options(function () {
                        $countries = Countries::getAllSelectedCountries();
                        $options = [];
                        foreach ($countries as $country) {
                            $options[$country] = $country;
                        }

                        return $options;
                    })
                    ->required(fn (Get $get) => $get('invoice_street'))
                    ->nullable()
                    ->lazy(),
            ])
            ->hiddenOn(ViewOrder::class)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        return $schema->schema($newSchema);
    }

    public static function table(Table $table): Table
    {
        $orderOrigins = [];
        foreach (Order::distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return $table
            ->columns([
                TextColumn::make('invoice_id')
                    ->label('Bestelling ID')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Betaalmethode')
                    ->toggleable()
                    ->getStateUsing(fn ($record) => Str::substr($record->payment_method, 0, 10)),
                TextColumn::make('payment_status')
                    ->label('Betaalstatus')
                    ->toggleable()
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->orderStatus()['status'])
                    ->colors([
                        'primary' => fn ($state): bool => $state === 'Lopende aankoop',
                        'danger' => fn ($state): bool => $state === 'Geannuleerd',
                        'warning' => fn ($state): bool => in_array($state, ['Gedeeltelijk betaald', 'Retour']),
                        'success' => fn ($state): bool => in_array($state, ['Betaald', 'Wachten op bevestiging betaling']),
                    ]),
                TextColumn::make('fulfillment_status')
                    ->label('Fulfillment status')
                    ->toggleable()
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->credit_for_order_id ? (Orders::getReturnStatusses()[$record->retour_status] ?? '') : (Orders::getFulfillmentStatusses()[$record->fulfillment_status] ?? ''))
                    ->colors([
                        'danger',
                        'success' => fn ($state): bool => ($state === 'Afgehandeld' || $state === 'Verzonden' || $state == 'Klaar om opgehaald te worden'),
                    ]),
                TextColumn::make('name')
                    ->label('Klant')
                    ->toggleable()
                    ->searchable(array_merge([
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
                    ], collect(ecommerce()->builder('customOrderFields'))
                        ->keys()
                        ->map(fn ($key) => Str::snake($key))
                        ->toArray()))
                    ->sortable(),
                TextColumn::make('total')
                    ->toggleable()
                    ->sortable()
                    ->label('Totaal')
                    ->formatStateUsing(fn ($state) => CurrencyHelper::formatPrice($state)),
                TextColumn::make('orderProducts.name')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn($record) => str($record->orderProducts->map(fn ($product) => $product->name . ' x ' . $product->quantity)->join(', '))->limit(30))
                    ->tooltip(fn ($record) => $record->orderProducts->map(fn ($product) => $product->name . ' x ' . $product->quantity)->join(', '))
                    ->label('Bestelde producten')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->toggleable()
                    ->label('Aangemaakt op')
                    ->getStateUsing(fn ($record) => $record->created_at->format('d-m-Y H:i'))
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->multiple()
                    ->form([
                        Select::make('values')
                            ->label('Status')
                            ->multiple()
                            ->options([
                                'paid' => 'Betaald',
                                'partially_paid' => 'Gedeeltelijk betaald',
                                'waiting_for_confirmation' => 'Wachten op bevestiging',
                                'pending' => 'Lopende aankoop',
                                'cancelled' => 'Geannuleerd',
                                'return ' => 'Retour',
                            ])
                            ->default(['paid', 'partially_paid', 'waiting_for_confirmation']),
                    ]),
                SelectFilter::make('fulfillment_status')
                    ->multiple()
                    ->options(Orders::getFulfillmentStatusses()),
                SelectFilter::make('retour_status')
                    ->multiple()
                    ->options(Orders::getReturnStatusses()),
                SelectFilter::make('order_origin')
                    ->multiple()
                    ->options($orderOrigins),
                Filter::make('start_date')
                    ->form([
                        DatePicker::make('start_date')
                            ->label('Startdatum'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['start_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            );
                    }),
                Filter::make('end_date')
                    ->form([
                        DatePicker::make('end_date')
                            ->label('Einddatum'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['end_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('quickActions')
                    ->button()
                    ->label('Acties')
                    ->color('primary')
                    ->modalContent(fn (Order $record): View => view(
                        'dashed-ecommerce-core::orders.quick-view-order',
                        ['order' => $record],
                    ))
                    ->extraModalFooterActions([
                        Action::make('changeFulfillmentStatus')
                            ->label('Verander fulfillment status')
                            ->color('primary')
                            ->fillForm(function ($record) {
                                return [
                                    'fulfillmentStatus' => $record->fulfillment_status,
                                ];
                            })
                            ->schema([
                                Select::make('fulfillmentStatus')
                                    ->label('Verander fulfilment status')
                                    ->options(Orders::getFulfillmentStatusses())
                                    ->required(),
                            ])
                            ->visible(fn ($record) => ! $record->credit_for_order_id)
                            ->action(function ($record, $data) {
                                if ($record->fulfillment_status == $data['fulfillmentStatus']) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Bestelling heeft al deze fulfillment status')
                                        ->send();

                                    return;
                                }

                                $record->changeFulfillmentStatus($data['fulfillmentStatus']);

                                $orderLog = new OrderLog();
                                $orderLog->order_id = $record->id;
                                $orderLog->user_id = Auth::user()->id;
                                $orderLog->tag = 'order.changed-fulfillment-status-to-' . $data['fulfillmentStatus'];
                                $orderLog->save();

                                Notification::make()
                                    ->success()
                                    ->title('Bestelling fulfillment status aangepast')
                                    ->send();
                            }),
                        Action::make('changeRetourFulfillmentStatus')
                            ->label('Verander retour fulfillment status')
                            ->color('primary')
                            ->fillForm(function ($record) {
                                return [
                                    'retourStatus' => $record->retour_status,
                                ];
                            })
                            ->schema([
                                Select::make('retourStatus')
                                    ->label('Verander retour status')
                                    ->options(Orders::getReturnStatusses())
                                    ->required(),
                            ])
                            ->visible(fn ($record) => $record->credit_for_order_id)
                            ->action(function ($record, $data) {
                                if ($record->retour_status == $data['retourStatus']) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Bestelling heeft al deze retour status')
                                        ->send();

                                    return;
                                }

                                $record->retour_status = $data['retourStatus'];
                                $record->save();

                                $orderLog = new OrderLog();
                                $orderLog->order_id = $record->id;
                                $orderLog->user_id = Auth::user()->id;
                                $orderLog->tag = 'order.changed-retour-status-to-' . $data['retourStatus'];
                                $orderLog->save();

                                Notification::make()
                                    ->success()
                                    ->title('Bestelling retour status aangepast')
                                    ->send();
                            }),
                        Action::make('sendConfirmationEmail')
                            ->label('Stuur bevestigingsmail')
                            ->color('primary')
                            ->fillForm(function ($record) {
                                return [
                                    'email' => $record->email,
                                ];
                            })
                            ->schema([
                                TextInput::make('email')
                                    ->label('Stuur de email naar')
                                    ->email()
                                    ->required(),
                            ])
                            ->action(function ($record, $data) {
                                Orders::sendNotification($record, $data['email'], auth()->user());

                                Notification::make()
                                    ->success()
                                    ->title('De bevestigingsmail is verstuurd')
                                    ->send();
                            }),
                        Action::make('createOrderLog')
                            ->label('Maak bestellings notitie')
                            ->color('primary')
                            ->fillForm(function ($record) {
                                return [
                                    'emailSubject' => 'Je bestelling is bijgewerkt',
                                ];
                            })
                            ->schema([
                                Toggle::make('publicForCustomer')
                                    ->label('Zichtbaar voor klant')
                                    ->default(false)
                                    ->reactive(),
                                Toggle::make('sendEmailToCustomer')
                                    ->label('Moet de klant een notificatie van deze notitie ontvangen?')
                                    ->default(false)
                                    ->visible(fn (Get $get) => $get('publicForCustomer'))
                                    ->reactive(),
                                TextInput::make('emailSubject')
                                    ->label('Onderwerp van de mail')
                                    ->visible(fn (Get $get) => $get('publicForCustomer') && $get('sendEmailToCustomer')),
                                FileUpload::make('images')
                                    ->name('Bestanden')
                                    ->multiple()
                                    ->downloadable()
                                    ->openable()
                                    ->reorderable()
                                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                                    ->directory('dashed/orders/logs/images')
                                    ->maxSize(50000),
                                Textarea::make('note')
                                    ->label('Notitie')
                                    ->placeholder('Typ hier je notitie')
                                    ->required()
                                    ->minLength(3)
                                    ->maxLength(1500)
                                    ->rows(3),
                            ])
                            ->action(function ($record, $data) {
                                $orderLog = new OrderLog();
                                $orderLog->order_id = $record->id;
                                $orderLog->user_id = Auth::user()->id;
                                $orderLog->tag = 'order.note.created';
                                $orderLog->note = $data['note'];
                                $orderLog->public_for_customer = $data['publicForCustomer'];
                                $orderLog->send_email_to_customer = $data['publicForCustomer'] && $data['sendEmailToCustomer'];
                                $orderLog->email_subject = $data['emailSubject'] ?? 'Je bestelling is bijgewerkt';

                                $orderLog->images = $data['images'];
                                $orderLog->save();

                                if ($orderLog->send_email_to_customer) {
                                    try {
                                        Mail::to($record->email)->send(new OrderNoteMail($record, $orderLog));
                                    } catch (\Exception $exception) {
                                    }
                                }

                                Notification::make()
                                    ->success()
                                    ->title('De notitie is aangemaakt')
                                    ->send();
                            }),
                    ])
                    ->modalHeading('Bestelling bekijken')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->action(function (Order $record, array $data): void {
                        if (isset($data['fulfillment_status'])) {
                            $record->fulfillment_status = $data['fulfillment_status'];
                        }
                        if (isset($data['retour_status'])) {
                            $record->retour_status = $data['retour_status'];
                        }
                        $record->save();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('downloadInvoices')
                    ->label('Download facturen')
                    ->color('primary')
                    ->action(function (Collection $records, array $data) {
                        $hash = Str::random();
                        $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

                        $hasPdf = false;
                        foreach ($records as $order) {
                            $url = $order->downloadInvoiceUrl();

                            if ($url) {
                                $invoice = Storage::disk('dashed')->get('dashed/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                                Storage::disk('public')->put('/dashed/tmp-exports/' . $hash . '/invoices-to-export/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf', $invoice);
                                $invoicePath = storage_path('app/public/dashed/tmp-exports/' . $hash . '/invoices-to-export/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                                $pdfMerger->addPDF($invoicePath, 'all');
                                $hasPdf = true;
                            }
                        }

                        if ($hasPdf) {
                            $pdfMerger->merge();

                            $invoicePath = '/dashed/tmp-exports/' . $hash . '/invoices/exported-invoice.pdf';
                            Storage::disk('public')->put($invoicePath, '');
                            $pdfMerger->save(storage_path('app/public' . $invoicePath));
                            Notification::make()
                                ->title('De export is gedownload')
                                ->success()
                                ->send();

                            return Storage::disk('public')->download($invoicePath);
                        } else {
                            Notification::make()
                                ->title('Geen facturen om te downloaden')
                                ->success()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('downloadPackingSlips')
                    ->label('Download pakbonnen')
                    ->color('primary')
                    ->action(function (Collection $records, array $data) {
                        $hash = Str::random();
                        $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

                        $hasPdf = false;
                        foreach ($records as $order) {
                            $url = $order->downloadPackingSlipUrl();

                            if ($url) {
                                $packingSlip = Storage::disk('dashed')->get('dashed/packing-slips/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                                Storage::disk('public')->put('/dashed/tmp-exports/' . $hash . '/packing-slips-to-export/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf', $packingSlip);
                                $packingSlipPath = storage_path('app/public/dashed/tmp-exports/' . $hash . '/packing-slips-to-export/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                                $pdfMerger->addPDF($packingSlipPath, 'all');
                                $hasPdf = true;
                            }
                        }

                        if ($hasPdf) {
                            $pdfMerger->merge();

                            $invoicePath = '/dashed/tmp-exports/' . $hash . '/packing-slips/exported-packing-slip.pdf';
                            Storage::disk('public')->put($invoicePath, '');
                            $pdfMerger->save(storage_path('app/public' . $invoicePath));
                            Notification::make()
                                ->title('De export is gedownload')
                                ->success()
                                ->send();

                            return Storage::disk('public')->download($invoicePath);
                        } else {
                            Notification::make()
                                ->title('Geen pakbonnen om te downloaden')
                                ->success()
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('changeFulfillmentStatus')
                    ->color('primary')
                    ->label('Fulfillment status')
                    ->schema([
                        Select::make('fulfillment_status')
                            ->label('Veranderd fulfillment status naar')
                            ->options(Orders::getFulfillmentStatusses())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        foreach ($records as $record) {
                            $record->changeFulfillmentStatus($data['fulfillment_status']);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->filtersFormColumns(4)
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
            'view' => ViewOrder::route('/{record}/view'),
        ];
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
