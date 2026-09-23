<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\Gs1RunLine;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1Assigner;
use Filament\Resources\RelationManagers\RelationManager;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1RunLockedException;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Codes');
    }

    /**
     * Op een ViewRecord-pagina is een relation manager standaard alleen-lezen,
     * en dan verdwijnen de beslis- en terugdraaiknoppen.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            Gs1RunLine::KIND_NAAM_MATCH => __('Gekoppeld op naam'),
            Gs1RunLine::KIND_POOL => __('Vrije code'),
            Gs1RunLine::KIND_WEES => __('Weesgeraakt'),
            Gs1RunLine::KIND_AL_GEKOPPELD => __('Al gekoppeld'),
            default => $kind,
        };
    }

    /**
     * De categorieën van het doelproduct, en anders van het verwijderde
     * product dat de code had.
     */
    public static function categoryNames(Gs1RunLine $record): ?string
    {
        $product = $record->product ?? $record->previousProduct;
        $names = $product?->productCategories->map(fn ($category) => (string) $category->name)->filter()->implode(', ');

        return $names ?: null;
    }

    public static function decisionOptions(): array
    {
        return [
            Gs1RunLine::DECISION_LATEN => __('Laten staan'),
            Gs1RunLine::DECISION_INACTIEF => __('Op Inactief zetten'),
            Gs1RunLine::DECISION_VRIJGEVEN => __('Vrijgeven voor hergebruik'),
            Gs1RunLine::DECISION_KOPPELEN => __('Koppelen aan een product'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product.productCategories', 'previousProduct.productCategories']))
            ->columns([
                TextColumn::make('gtin')->label(__('GTIN'))->searchable()->fontFamily('mono'),
                TextColumn::make('kind')->label(__('Soort'))->badge()
                    ->formatStateUsing(fn (string $state) => static::kindLabel($state)),
                TextColumn::make('gs1_status')->label(__('Status bij GS1')),
                TextColumn::make('gs1_description')->label(__('Omschrijving bij GS1'))->limit(50)->searchable(),
                TextColumn::make('decision')->label(__('Beslissing'))
                    ->formatStateUsing(fn (?string $state) => static::decisionOptions()[$state] ?? ($state === Gs1RunLine::DECISION_TOEWIJZEN ? __('Toewijzen') : $state)),
                TextColumn::make('product.name')->label(__('Product'))->placeholder('-'),
                TextColumn::make('previousProduct.name')->label(__('Verwijderd product'))->placeholder('-'),
                TextColumn::make('category')->label(__('Categorie'))->placeholder('-')->wrap()
                    ->getStateUsing(fn (Gs1RunLine $record) => static::categoryNames($record)),
                IconColumn::make('reuse')->label(__('Hergebruik'))->boolean()
                    ->getStateUsing(fn (Gs1RunLine $record) => $record->isReuse()),
                TextColumn::make('applied_at')->label(__('Toegepast'))->dateTime('d-m-Y H:i')->placeholder('-'),
                TextColumn::make('reverted_at')->label(__('Teruggedraaid'))->dateTime('d-m-Y H:i')->placeholder('-'),
                TextColumn::make('skip_reason')->label(__('Overgeslagen'))->placeholder('-')->wrap(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('Soort'))->options([
                    Gs1RunLine::KIND_NAAM_MATCH => static::kindLabel(Gs1RunLine::KIND_NAAM_MATCH),
                    Gs1RunLine::KIND_POOL => static::kindLabel(Gs1RunLine::KIND_POOL),
                    Gs1RunLine::KIND_WEES => static::kindLabel(Gs1RunLine::KIND_WEES),
                    Gs1RunLine::KIND_AL_GEKOPPELD => static::kindLabel(Gs1RunLine::KIND_AL_GEKOPPELD),
                ]),
            ])
            ->headerActions([
                Action::make('decideAll')
                    ->label(__('Beslissing voor alle weesgeraakte codes'))
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->visible(fn () => $this->getOwnerRecord()->isConcept()
                        && $this->getOwnerRecord()->lines()->where('kind', Gs1RunLine::KIND_WEES)->exists())
                    ->schema([
                        Select::make('decision')
                            ->label(__('Beslissing'))
                            ->options(array_diff_key(static::decisionOptions(), [Gs1RunLine::DECISION_KOPPELEN => true]))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->getOwnerRecord()->lines()
                            ->where('kind', Gs1RunLine::KIND_WEES)
                            ->update(['decision' => $data['decision'], 'product_id' => null]);
                    }),
            ])
            ->recordActions([
                Action::make('decide')
                    ->label(__('Beslissen'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->visible(fn (Gs1RunLine $record) => $record->kind === Gs1RunLine::KIND_WEES && $this->getOwnerRecord()->isConcept())
                    ->fillForm(fn (Gs1RunLine $record) => [
                        'decision' => $record->decision,
                        // Een lege zoekterm levert het eerste het beste product op, dus
                        // alleen voorstellen als er een omschrijving is.
                        'product_id' => $record->product_id ?? (filled($record->gs1_description)
                            ? array_key_first(RelationshipSearchQuery::make(Product::class, (string) $record->gs1_description, applyScopes: 'needsGs1Code'))
                            : null),
                    ])
                    ->schema([
                        Select::make('decision')
                            ->label(__('Beslissing'))
                            ->options(static::decisionOptions())
                            ->live()
                            ->required(),
                        Select::make('product_id')
                            ->label(__('Product'))
                            ->helperText(__('Alleen producten zonder EAN. Voorgesteld op de omschrijving bij GS1.'))
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => RelationshipSearchQuery::make(Product::class, $search, applyScopes: 'needsGs1Code'))
                            // Zonder label keurt Filament de keuze af, dus een product met EAN valt af.
                            ->getOptionLabelUsing(fn ($value) => Product::query()->needsGs1Code()->find($value)?->name)
                            ->visible(fn (Get $get) => $get('decision') === Gs1RunLine::DECISION_KOPPELEN)
                            ->required(fn (Get $get) => $get('decision') === Gs1RunLine::DECISION_KOPPELEN),
                    ])
                    ->action(function (Gs1RunLine $record, array $data) {
                        $record->update([
                            'decision' => $data['decision'],
                            'product_id' => $data['decision'] === Gs1RunLine::DECISION_KOPPELEN ? (int) $data['product_id'] : null,
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('revert')
                    ->label(__('Terugdraaien'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->authorize(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('Het product verliest de EAN en de code gaat terug naar het verwijderde product, als dat er was. Het uploadbestand wordt niet opnieuw gemaakt.'))
                    ->visible(fn () => $this->getOwnerRecord()->isToegewezen())
                    ->action(function (Collection $records) {
                        try {
                            $count = $records->filter(fn (Gs1RunLine $line) => app(Gs1Assigner::class)->revert($line))->count();
                        } catch (Gs1RunLockedException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()
                            ->title(__(':aantal regels teruggedraaid', ['aantal' => $count]))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
