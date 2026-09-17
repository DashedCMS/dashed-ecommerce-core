<?php

namespace Dashed\DashedEcommerceCore\Filament\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedCore\Classes\QueryHelpers\RelationshipSearchQuery;

/**
 * Bulk-actie om categorieën toe te wijzen aan geselecteerde producten of
 * productgroepen. Toevoegen en vervangen activeren net als het bewerkformulier
 * de bovenliggende categorieën mee. Bij een productgroep wordt daarna de
 * productsync klaargezet, want een pivotwijziging vuurt de saved-hook van de
 * groep niet en anders lopen de categorieën niet door naar de producten.
 */
class BulkAssignCategoriesBulkAction
{
    public const MODE_ADD = 'add';
    public const MODE_REPLACE = 'replace';
    public const MODE_REMOVE = 'remove';

    public static function make(): BulkAction
    {
        return BulkAction::make('assignCategories')
            ->color('primary')
            ->icon('heroicon-o-tag')
            ->label(__('Categorieën toewijzen'))
            ->modalHeading(__('Categorieën toewijzen aan geselecteerde items'))
            ->modalSubmitActionLabel(__('Doorvoeren'))
            ->schema([
                Select::make('categories')
                    ->label(__('Categorieën'))
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn ($search) => RelationshipSearchQuery::make(ProductCategory::class, $search))
                    ->getOptionLabelsUsing(fn (array $values) => ProductCategory::query()->whereIn('id', $values)->get()->mapWithKeys(fn ($category) => [$category->id => $category->nameWithParents])->toArray())
                    ->helperText(__('Bovenliggende categorieën worden automatisch meegenomen. Let op: bij een product waarvan de productgroep categorieën synchroniseert, zet de eerstvolgende sync ze weer terug.')),
                Radio::make('mode')
                    ->label(__('Wat moet er gebeuren'))
                    ->options([
                        self::MODE_ADD => __('Toevoegen aan de huidige categorieën'),
                        self::MODE_REPLACE => __('Huidige categorieën vervangen'),
                        self::MODE_REMOVE => __('Deze categorieën weghalen'),
                    ])
                    ->default(self::MODE_ADD)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $touched = static::applyToRecords($records, $data);

                Notification::make()
                    ->title(__(':aantal item(s) bijgewerkt', ['aantal' => $touched]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function applyToRecords(Collection $records, array $data): int
    {
        $mode = (string) ($data['mode'] ?? self::MODE_ADD);
        $categoryIds = array_map('intval', $data['categories'] ?? []);

        if ($mode !== self::MODE_REMOVE) {
            $categoryIds = ProductCategories::getFromIdsWithParents($categoryIds)->pluck('id')->all();
        }

        foreach ($records as $record) {
            match ($mode) {
                self::MODE_REPLACE => $record->productCategories()->sync($categoryIds),
                self::MODE_REMOVE => $record->productCategories()->detach($categoryIds),
                default => $record->productCategories()->syncWithoutDetaching($categoryIds),
            };

            if ($record instanceof ProductGroup) {
                UpdateProductInformationJob::dispatch($record)->onQueue('ecommerce');
            }
        }

        return $records->count();
    }
}
