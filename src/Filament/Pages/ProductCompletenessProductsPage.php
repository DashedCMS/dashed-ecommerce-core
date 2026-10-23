<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

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
use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Concerns\ReadsProductCompleteness;

/**
 * Productcontrole per product (variant): wat elk product mist, met filters
 * op productgroep, categorie van de controle en losse controles.
 */
class ProductCompletenessProductsPage extends Page implements HasTable
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;
    use InteractsWithTable;
    use ReadsProductCompleteness;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $title = 'Productcontrole: producten';

    protected static ?string $slug = 'productcontrole/producten';

    protected string $view = 'dashed-ecommerce-core::filament.pages.product-completeness-products';

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('groups')
                ->label(__('Per productgroep'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProductCompletenessPage::getUrl()),
            $this->configureChecksAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection, int | string $page, int | string $recordsPerPage) => $this->paginateRows(
                $this->productRows($search, $filters ?? []),
                $sortColumn,
                $sortDirection,
                $page,
                $recordsPerPage,
            ))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Product'))
                    ->description(fn (array $record) => $record['group_name'])
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('public')
                    ->label(__('Openbaar'))
                    ->boolean(),
                TextColumn::make('missing_labels')
                    ->label(__('Mist'))
                    ->badge()
                    ->color(fn (string $state, array $record) => $this->checkColor(array_search($state, $record['missing_labels'], true) ?: ''))
                    ->state(fn (array $record) => array_values($record['missing_labels']))
                    ->placeholder(__('Compleet'))
                    ->limitList(5)
                    ->expandableLimitedList()
                    ->wrap(),
                TextColumn::make('missing_count')
                    ->label(__('Aantal'))
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label(__('Productgroep'))
                    ->searchable()
                    ->options(fn () => collect($this->completenessReport()['groups'])
                        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                        ->mapWithKeys(fn (array $group) => [$group['id'] => $group['name']])
                        ->all()),
                TernaryFilter::make('public')
                    ->label(__('Openbaar')),
                SelectFilter::make('category')
                    ->label(__('Soort'))
                    ->options(ProductCompleteness::categories()),
                SelectFilter::make('checks')
                    ->label(__('Mist'))
                    ->multiple()
                    ->options(fn () => $this->checkOptions()),
                Filter::make('with_issues')
                    ->label(__('Alleen met gaten'))
                    ->toggle()
                    ->default(),
            ])
            ->recordUrl(fn (array $record) => ProductResource::getUrl('edit', ['record' => $record['id']]), shouldOpenInNewTab: true)
            ->defaultSort('missing_count', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->emptyStateHeading(__('Niets gevonden'))
            ->emptyStateDescription(__('Geen producten die aan de filters voldoen.'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function productRows(?string $search, array $filters): array
    {
        $checks = $this->activeChecks();
        $groupId = $filters['group']['value'] ?? null;
        $category = $filters['category']['value'] ?? null;
        $selected = array_values(array_filter($filters['checks']['values'] ?? []));
        $onlyWithIssues = (bool) ($filters['with_issues']['isActive'] ?? true);

        $rows = [];
        foreach ($this->completenessReport()['products'] as $row) {
            if ($groupId && (string) $row['group_id'] !== (string) $groupId) {
                continue;
            }
            if (! $this->matchesPublic($row, $filters['public']['value'] ?? null)
                || ! $this->matchesSearch($row, $search, ['name', 'group_name', 'sku', 'ean'])) {
                continue;
            }

            $missing = $this->activeMissing($row['missing']);
            if ($category) {
                $missing = array_values(array_filter($missing, fn (string $key) => $checks[$key]['category'] === $category));
            }
            if ($selected && ! array_intersect($selected, $missing)) {
                continue;
            }
            if ($onlyWithIssues && ! $missing) {
                continue;
            }

            $row['missing_labels'] = [];
            foreach ($missing as $key) {
                $row['missing_labels'][$key] = $checks[$key]['label'];
            }
            $row['missing_count'] = count($missing);

            $rows[] = $row;
        }

        return $rows;
    }
}
