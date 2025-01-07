<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\EditFulfillmentCompany;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\ListFulfillmentCompany;
use Dashed\DashedEcommerceCore\Filament\Resources\FulfillmentCompanyResource\Pages\CreateFulfillmentCompany;

class FulfillmentCompanyResource extends Resource
{
    use Translatable;

    protected static ?string $model = FulfillmentCompany::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $navigationLabel = 'Fulfillment bedrijven';
    protected static ?string $label = 'Fulfillment bedrijf';
    protected static ?string $pluralLabel = 'Fulfillment bedrijven';
    protected static ?int $navigationSort = 1000;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
//                Section::make('Globale informatie')
//                    ->schema([
//                        Select::make('site_id')
//                            ->label('Actief op site')
//                            ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
//                            ->hidden(function () {
//                                return ! (Sites::getAmountOfSites() > 1);
//                            })
//                            ->required(),
//                    ])
//                    ->hidden(function () {
//                        return ! (Sites::getAmountOfSites() > 1);
//                    })
//                    ->collapsed(fn ($livewire) => $livewire instanceof EditPaymentMethod),
//                Section::make('Betaalmethode')
//                    ->schema($contentSchema),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Naam')
                    ->searchable(query: SearchQuery::make())
                    ->sortable(),
                TextColumn::make('products_count')
                    ->label('Aantal producten')
                    ->counts('products')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->button(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFulfillmentCompany::route('/'),
            'create' => CreateFulfillmentCompany::route('/create'),
            'edit' => EditFulfillmentCompany::route('/{record}/edit'),
        ];
    }
}
