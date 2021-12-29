<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources;

use Filament\Resources\Form;
use Filament\Resources\Table;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Tables\Columns\BooleanColumn;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\EditOrder;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages\ListOrders;
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

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable([
                        'name',
                        'short_description',
                        'description',
                        'search_terms',
                        'content',
                        'meta_title',
                        'meta_description',
                    ])
                    ->sortable(),
                TagsColumn::make('site_ids')
                    ->label('Actief op site(s)')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('total_purchases')
                    ->label('Aantal verkopen'),
                BooleanColumn::make('status')
                    ->label('Status'),
            ])
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
//            'create' => CreateOrder::route('/create'),
//            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
