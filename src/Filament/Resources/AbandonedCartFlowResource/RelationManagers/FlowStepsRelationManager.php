<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\RelationManagers;

use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\RichEditor;
use Dashed\DashedEcommerceCore\Models\Cart;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Mail\AbandonedCartMail;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Filament\Resources\RelationManagers\RelationManager;
use LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable;

class FlowStepsRelationManager extends RelationManager
{
    use Translatable;

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
                IconColumn::make('incentive_enabled')->label('Korting')->boolean(),
                IconColumn::make('enabled')->label('Actief')->boolean(),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema($this->stepSchema()),
                Action::make('sendTest')
                    ->label('Stuur test')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('gray')
                    ->schema([
                        TextInput::make('test_email')
                            ->label('E-mailadres')
                            ->email()
                            ->required()
                            ->default(fn () => auth()->user()?->email),
                    ])
                    ->action(function (array $data, $record): void {
                        $cart = Cart::with(['items', 'items.product', 'items.product.productGroup'])
                            ->whereHas('items')
                            ->latest()
                            ->first();

                        if (! $cart) {
                            Notification::make()
                                ->title('Geen winkelwagen met producten gevonden om te simuleren.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $discountCode = null;
                        if ($record->incentive_enabled && $record->incentive_value > 0) {
                            $prefix = $record->flow?->discount_prefix ?: 'TERUG';
                            $discountCode = DiscountCode::create([
                                'name' => 'Test - Verlaten winkelwagen',
                                'code' => $prefix . '-TEST-' . strtoupper(Str::random(6)),
                                'type' => $record->incentive_type === 'percentage' ? 'percentage' : 'amount',
                                'discount_amount' => $record->incentive_type === 'amount' ? $record->incentive_value : 0,
                                'discount_percentage' => $record->incentive_type === 'percentage' ? $record->incentive_value : 0,
                                'use_stock' => true,
                                'stock' => 1,
                                'stock_used' => 0,
                                'limit_use_per_customer' => true,
                                'start_date' => now(),
                                'end_date' => now()->addDays($record->incentive_valid_days ?? 7),
                                'site_ids' => [Sites::getActive()],
                            ]);
                        }

                        try {
                            Mail::to($data['test_email'])->send(new AbandonedCartMail($cart, $record, $discountCode));
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Fout bij versturen: ' . $e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Test email verzonden naar ' . $data['test_email'])
                            ->success()
                            ->send();
                    }),
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
                LocaleSwitcher::make(),
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
                    Toggle::make('enabled')
                        ->label('Stap inschakelen')
                        ->default(true),
                ])
                ->columns(3),

            Section::make('Email inhoud')
                ->schema([
                    TextInput::make('subject')
                        ->label('Onderwerpregel')
                        ->helperText('Beschikbare variabelen: :product: :siteName: :cartTotal:')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Builder::make('blocks')
                        ->label('Inhoud blokken')
                        ->blocks([
                            Builder\Block::make('text')
                                ->label('Tekst')
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    RichEditor::make('content')
                                        ->label('Tekst')
                                        ->helperText('Variabelen: :product: :siteName: :cartTotal:')
                                        ->toolbarButtons([
                                            'bold', 'italic', 'underline', 'strike',
                                            'link', 'bulletList', 'orderedList', 'h2', 'h3',
                                        ]),
                                ]),
                            Builder\Block::make('product')
                                ->label('Hoofdproduct')
                                ->icon('heroicon-o-shopping-bag')
                                ->maxItems(1)
                                ->schema([]),
                            Builder\Block::make('products')
                                ->label('Alle producten')
                                ->icon('heroicon-o-shopping-cart')
                                ->maxItems(1)
                                ->schema([]),
                            Builder\Block::make('review')
                                ->label('Klantreview')
                                ->icon('heroicon-o-star')
                                ->maxItems(1)
                                ->schema([]),
                            Builder\Block::make('discount')
                                ->label('Kortingscode')
                                ->icon('heroicon-o-tag')
                                ->maxItems(1)
                                ->schema([]),
                            Builder\Block::make('button')
                                ->label('Knop')
                                ->icon('heroicon-o-cursor-arrow-rays')
                                ->schema([
                                    TextInput::make('label')
                                        ->label('Knoptekst')
                                        ->default('Bestel nu')
                                        ->required()
                                        ->maxLength(100),
                                ]),
                            Builder\Block::make('divider')
                                ->label('Scheidingslijn')
                                ->icon('heroicon-o-minus')
                                ->schema([]),
                            Builder\Block::make('usp')
                                ->label('USPs')
                                ->icon('heroicon-o-check-badge')
                                ->maxItems(1)
                                ->schema([
                                    Textarea::make('items')
                                        ->label('USPs (één per regel)')
                                        ->helperText('Voer elke USP op een nieuwe regel in')
                                        ->rows(4)
                                        ->default("Gratis verzending\nSnel geleverd\nVeilig betalen"),
                                ]),
                        ])
                        ->columnSpanFull()
                        ->collapsible()
                        ->reorderableWithButtons(),
                ]),

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
