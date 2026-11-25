<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Excel as ExcelType;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Exports\OpenOrderProducts;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

class ListOpenOrderProducts extends ListRecords
{
    protected static string $resource = OpenOrderProductResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    public function getTabs(): array
    {
        // De gegroepeerde tabs passen de query hier bewust niet aan: het
        // samenvoegen gebeurt pas in filterTableQuery(), ná filters en zoeken,
        // zodat die op de losse regels werken en het aantal (som) klopt.
        return [
            'all' => Tab::make('Per orderregel')
                ->modifyQueryUsing(fn (Builder $query): Builder => OpenOrderProductResource::priorityFirst($query)),
            'grouped' => Tab::make('Gegroepeerd per product'),
            'grouped_product_group' => Tab::make('Gegroepeerd per productgroep'),
        ];
    }

    public function isGroupedTab(): bool
    {
        return in_array($this->activeTab ?? null, OpenOrderProductResource::GROUPED_TABS, true);
    }

    /**
     * Filters en zoeken op de losse regels; in een gegroepeerde tab daarna pas
     * samenvoegen tot één rij per product of productgroep. Sorteren en pagineren
     * (die Filament hierna toepast) werken dan op de samengevoegde rijen.
     */
    public function filterTableQuery(Builder $query): Builder
    {
        $query = parent::filterTableQuery($query);

        if (! $this->isGroupedTab()) {
            return $query;
        }

        return OpenOrderProductResource::groupLines($query, $this->activeTab)
            ->with(['order', 'product']);
    }

    /**
     * In een gegroepeerde tab zijn de geselecteerde sleutels MIN(id) van een
     * groep. Die moeten op de samengevoegde rijen worden toegepast, niet op de
     * regels (dan zou een groep na het deselecteren van zijn eerste regel
     * gewoon met een nieuwe MIN(id) terugkomen).
     */
    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        if (! $this->isGroupedTab()) {
            return parent::getSelectedTableRecordsQuery($shouldFetchSelectedRecords, $chunkSize);
        }

        $query = $this->getFilteredTableQuery();

        return $this->isTrackingDeselectedTableRecords
            ? $query->whereKeyNot($this->deselectedTableRecords)
            : $query->whereKey($this->selectedTableRecords);
    }

    /** De regelquery met de actieve filters en zoekterm, zonder samenvoegen. */
    public function getFilteredLineQuery(): Builder
    {
        $query = $this->getTable()->getQuery();
        $this->applyFiltersToTableQuery($query);
        $this->applySearchToTableQuery($query);

        return $query;
    }

    /**
     * De echte orderregels achter de gegeven tabelrijen. Per orderregel zijn
     * dat de rijen zelf; in een gegroepeerde tab alle openstaande, nog niet
     * klare regels van de gekozen producten/productgroepen die door de actieve
     * filters en zoekterm komen. Bedoeld voor (app-)bulkacties.
     *
     * @return EloquentCollection<int, OrderProduct>
     */
    public function openLinesForRecords(iterable $records): EloquentCollection
    {
        $records = collect($records);

        if (! $this->isGroupedTab()) {
            return OrderProduct::query()
                ->whereKey($records->map(fn ($record) => $record instanceof OrderProduct ? $record->getKey() : $record)->all())
                ->with('order')
                ->get();
        }

        $groupKeys = $records
            ->map(fn ($record) => $record instanceof OrderProduct ? $record->getAttribute('group_key') : null)
            ->reject(fn ($key) => $key === null)
            ->unique()
            ->values()
            ->all();

        return OpenOrderProductResource::linesInGroups($this->getFilteredLineQuery(), $this->activeTab, $groupKeys)
            ->with('order')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label(__('Exporteer Excel'))
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $mode = $this->resolveExportMode();
                    $base = 'Openstaande bestellingen - '
                        . (Customsetting::get('site_name') ?: 'Webshop')
                        . ' - ' . now()->format('Y-m-d');
                    $suffix = match ($mode) {
                        'grouped' => ' (gegroepeerd)',
                        'grouped_product_group' => ' (gegroepeerd per productgroep)',
                        default => '',
                    };

                    return Excel::download(
                        new OpenOrderProducts($mode),
                        $base . $suffix . '.xlsx',
                        ExcelType::XLSX,
                    );
                }),
        ];
    }

    private function resolveExportMode(): string
    {
        return match ($this->activeTab ?? 'all') {
            'grouped' => 'grouped',
            'grouped_product_group' => 'grouped_product_group',
            default => 'all',
        };
    }
}
