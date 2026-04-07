<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;

class FlowStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';
    protected static ?string $title = 'Email stappen';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->width(40),
                TextColumn::make('delay_label')
                    ->label('Vertraging')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject')
                    ->label('Onderwerp')
                    ->limit(50)
                    ->weight('bold'),
                IconColumn::make('show_products')->label('Producten')->boolean(),
                IconColumn::make('show_review')->label('Review')->boolean(),
                IconColumn::make('incentive_enabled')->label('Korting')->boolean(),
                IconColumn::make('enabled')->label('Actief')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema($this->stepSchema()),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->label('Stap toevoegen')
                    ->schema($this->stepSchema())
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] = $this->getOwnerRecord()->steps()->max('sort_order') + 1;
                        return $data;
                    }),
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    protected function stepSchema(): array
    {
        return [
            Section::make('Timing')
                ->schema([
                    TextInput::make('delay_value')
                        ->label('Vertraging')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->default(1),
                    Select::make('delay_unit')
                        ->label('Eenheid')
                        ->options([
                            'hours' => 'Uur',
                            'days' => 'Dagen',
                        ])
                        ->default('hours')
                        ->required(),
                ])
                ->columns(2),

            Section::make('Email inhoud')
                ->schema([
                    TextInput::make('subject')
                        ->label('Onderwerpregel')
                        ->helperText('Gebruik :product voor de naam van het eerste product.')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    RichEditor::make('intro_text')
                        ->label('Berichttekst')
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'link', 'bulletList', 'orderedList', 'h2', 'h3',
                        ])
                        ->columnSpanFull(),
                    TextInput::make('button_label')
                        ->label('Knoptekst')
                        ->default('Bestel nu')
                        ->required()
                        ->maxLength(100),
                ]),

            Section::make('Opties')
                ->schema([
                    Toggle::make('show_products')
                        ->label('Producten tonen')
                        ->default(true),
                    Toggle::make('show_review')
                        ->label('Klantreview tonen'),
                    Toggle::make('enabled')
                        ->label('Stap inschakelen')
                        ->default(true),
                ])
                ->columns(3),

            Section::make('Kortingscode')
                ->schema([
                    Toggle::make('incentive_enabled')
                        ->label('Kortingscode toevoegen')
                        ->live(),
                    Select::make('incentive_type')
                        ->label('Type korting')
                        ->options([
                            'amount' => 'Vast bedrag (€)',
                            'percentage' => 'Percentage (%)',
                        ])
                        ->default('amount')
                        ->visible(fn (Get $get) => $get('incentive_enabled')),
                    TextInput::make('incentive_value')
                        ->label('Kortingswaarde')
                        ->numeric()
                        ->minValue(0)
                        ->default(5)
                        ->visible(fn (Get $get) => $get('incentive_enabled')),
                    TextInput::make('incentive_valid_days')
                        ->label('Geldig (dagen)')
                        ->numeric()
                        ->minValue(1)
                        ->default(7)
                        ->suffix('dagen')
                        ->visible(fn (Get $get) => $get('incentive_enabled')),
                ])
                ->columns(2),
        ];
    }
}
