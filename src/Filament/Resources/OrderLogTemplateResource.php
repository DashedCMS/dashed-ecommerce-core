<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources;

use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\Concerns\Translatable;
use Filament\Tables\Actions\DeleteBulkAction;
use Dashed\DashedCore\Classes\QueryHelpers\SearchQuery;
use Dashed\DashedEcommerceCore\Models\OrderLogTemplate;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages\EditPaymentMethod;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages\EditOrderLogTemplate;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages\ListOrderLogTemplates;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages\CreateOrderLogTemplate;

class OrderLogTemplateResource extends Resource
{
    use Translatable;

    protected static ?string $model = OrderLogTemplate::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationGroup = 'Content';
    protected static ?string $navigationLabel = 'Bestel log templates';
    protected static ?string $label = 'Bestel log template';
    protected static ?string $pluralLabel = 'Bestel log templates';

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
        ];
    }

    public static function form(Form $form): Form
    {
        $contentSchema = [
            Placeholder::make('name')
                ->label('Variabelen')
                ->helperText('Je kan de volgende variablen gebruiken in de mails: ' . implode(', ', OrderVariableReplacer::getAvailableVariables())),
            TextInput::make('name')
                ->label('Naam')
                ->required()
                ->maxLength(100),
            TextInput::make('subject')
                ->label('Onderwerp')
                ->required()
                ->maxLength(200),
            cms()->editorField('body', 'Inhoud')
                ->required(),
        ];

        return $form
            ->schema([
                Section::make('Globale informatie')
                    ->schema([
                        Select::make('site_id')
                            ->label('Actief op site')
                            ->options(collect(Sites::getSites())->pluck('name', 'id')->toArray())
                            ->hidden(function () {
                                return ! (Sites::getAmountOfSites() > 1);
                            })
                            ->required(),
                    ])
                    ->hidden(function () {
                        return ! (Sites::getAmountOfSites() > 1);
                    })
                    ->collapsed(fn ($livewire) => $livewire instanceof EditPaymentMethod),
                Section::make('Betaalmethode')
                    ->schema($contentSchema),
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
                TextColumn::make('site_id')
                    ->label('Actief op site')
                    ->sortable()
                    ->hidden(! (Sites::getAmountOfSites() > 1))
                    ->searchable(),
                TextColumn::make('psp')
                    ->label('PSP')
                    ->sortable()
                    ->searchable(),
                ImageColumn::make('image')
                    ->label('Afbeelding')
                    ->getStateUsing(fn ($record) => $record->image ? (mediaHelper()->getSingleMedia($record->image)->url ?? '') : ''),
                IconColumn::make('active')
                    ->label('Actief')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

            ])
            ->filters([
                //
            ])
            ->reorderable('order')
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
            'index' => ListOrderLogTemplates::route('/'),
            'create' => CreateOrderLogTemplate::route('/create'),
            'edit' => EditOrderLogTemplate::route('/{record}/edit'),
        ];
    }
}
