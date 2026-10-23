<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Livewire\Attributes\Url;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Concerns\InteractsWithTable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Concerns\ReadsProductCompleteness;

/**
 * Productcontrole per productgroep: wat de groep zelf mist en hoeveel van
 * zijn producten iets missen. Een rij opent de producten van die groep.
 */
class ProductCompletenessPage extends Page implements HasTable
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use InteractsWithTable;
    use ReadsProductCompleteness;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Producten';

    protected static ?string $title = 'Productcontrole';

    protected static ?string $slug = 'productcontrole';

    protected static ?int $navigationSort = 50;

    protected string $view = 'dashed-ecommerce-core::filament.pages.product-completeness';

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    /** @var array<int, int>|null */
    protected ?array $issuesPerGroup = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allProducts')
                ->label(__('Alle producten'))
                ->icon('heroicon-o-list-bullet')
                ->url(ProductCompletenessProductsPage::getUrl()),
            $this->configureChecksAction(),
        ];
    }

    /**
     * Aantal producten per controle, voor de samenvatting boven de tabel.
     *
     * @return array<int, array{key: string, label: string, color: string, count: int, url: string}>
     */
    public function summary(): array
    {
        $counts = [];
        foreach ($this->completenessReport()['products'] as $row) {
            foreach ($this->activeMissing($row['missing']) as $key) {
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $summary = [];
        foreach ($this->activeChecks() as $key => $check) {
            if (! ($counts[$key] ?? 0)) {
                continue;
            }

            $summary[] = [
                'key' => $key,
                'label' => $check['label'],
                'color' => $this->checkColor($key),
                'count' => $counts[$key],
                'url' => ProductCompletenessProductsPage::getUrl(['filters' => ['checks' => ['values' => [$key]], 'with_issues' => ['isActive' => true]]]),
            ];
        }

        return $summary;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection, int | string $page, int | string $recordsPerPage) => $this->paginateRows(
                $this->groupRows($search, $filters ?? []),
                $sortColumn,
                $sortDirection,
                $page,
                $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Productgroep'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                IconColumn::make('public')
                    ->label(__('Openbaar'))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('product_count')
                    ->label(__('Producten'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('products_with_issues')
                    ->label(__('Producten met gaten'))
                    ->numeric()
                    ->sortable()
                    ->color(fn ($state) => $state ? 'danger' : 'success'),
                TextColumn::make('own')
                    ->label(__('Groep mist'))
                    ->badge()
                    ->color(fn (string $state, array $record) => $this->checkColor(array_search($state, $record['own_labels'], true) ?: ''))
                    ->state(fn (array $record) => array_values($record['own_labels']))
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('product_issues')
                    ->label(__('Producten missen'))
                    ->badge()
                    ->color('gray')
                    ->state(fn (array $record) => $record['product_issue_labels'])
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('missing_count')
                    ->label(__('Totaal'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('public')
                    ->label(__('Openbaar')),
                SelectFilter::make('checks')
                    ->label(__('Mist'))
                    ->multiple()
                    ->options(fn () => $this->checkOptions()),
                Filter::make('with_issues')
                    ->label(__('Alleen met gaten'))
                    ->toggle()
                    ->default(),
            ])
            ->recordUrl(fn (array $record) => ProductCompletenessProductsPage::getUrl(['filters' => ['group' => ['value' => $record['id']], 'with_issues' => ['isActive' => true]]]))
            ->recordActions([
                Action::make('edit')
                    ->label(__('Bewerk groep'))
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (array $record) => ProductGroupResource::getUrl('edit', ['record' => $record['id']]))
                    ->iconButton()
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('missing_count', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->emptyStateHeading(__('Niets gevonden'))
            ->emptyStateDescription(__('Geen productgroepen die aan de filters voldoen.'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function groupRows(?string $search, array $filters): array
    {
        $checks = $this->activeChecks();
        $selected = array_values(array_filter($filters['checks']['values'] ?? []));
        $onlyWithIssues = (bool) ($filters['with_issues']['isActive'] ?? true);

        $rows = [];
        foreach ($this->completenessReport()['groups'] as $row) {
            if (! $this->matchesSearch($row, $search, ['name']) || ! $this->matchesPublic($row, $filters['public']['value'] ?? null)) {
                continue;
            }

            $own = $this->activeMissing($row['missing']);
            $productIssues = array_intersect_key($row['product_issues'], $checks);

            if ($selected && ! array_intersect($selected, array_merge($own, array_keys($productIssues)))) {
                continue;
            }

            $row['own_labels'] = [];
            foreach ($own as $key) {
                $row['own_labels'][$key] = $checks[$key]['label'];
            }
            $row['product_issue_labels'] = [];
            foreach ($productIssues as $key => $count) {
                $row['product_issue_labels'][] = $checks[$key]['label'] . ': ' . $count;
            }
            $row['missing_count'] = count($own) + array_sum($productIssues);
            $row['products_with_issues'] = $this->productsWithActiveIssues($row['id']);

            if ($onlyWithIssues && $row['missing_count'] === 0) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Producten met minstens één gat dat meetelt; de telling in het rapport
     * kent de uitgeschakelde controles niet.
     */
    private function productsWithActiveIssues(int $groupId): int
    {
        if ($this->issuesPerGroup === null) {
            $this->issuesPerGroup = [];
            foreach ($this->completenessReport()['products'] as $product) {
                if ($product['group_id'] && $this->activeMissing($product['missing'])) {
                    $this->issuesPerGroup[$product['group_id']] = ($this->issuesPerGroup[$product['group_id']] ?? 0) + 1;
                }
            }
        }

        return $this->issuesPerGroup[$groupId] ?? 0;
    }
}
