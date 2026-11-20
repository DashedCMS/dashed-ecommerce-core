<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Classes\OrderListTabs;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\ExportOrdersPage;
use Dashed\DashedEcommerceCore\Filament\Widgets\Orders\OrderWorkflowStatsWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Orders\OrderOutstandingStatsWidget;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected Width | string | null $maxContentWidth = 'full';

    /**
     * Tab counts for this request; not a public property, so every Livewire
     * request (filter, search, tab change) counts again exactly once.
     *
     * @var array<string, int>|null
     */
    protected ?array $tabCounts = null;

    /**
     * True while the retouren counter builds its query, so the status filter
     * steps aside exactly as it does on the retouren tab itself.
     */
    protected bool $countingReturns = false;

    protected function getHeaderActions(): array
    {
        $failedCount = collect(ecommerce()->shippingLabelProviders())
            ->sum(fn ($provider) => count($provider->failedOrders()));

        $actions = [];

        // Links to the existing, permission-guarded order export (view_exports).
        $actions[] = Action::make('exportOrders')
            ->label(__('Exporteren'))
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => ExportOrdersPage::canAccess())
            ->url(fn (): string => ExportOrdersPage::getUrl());

        $actions[] = CreateAction::make();

        if ($failedCount > 0) {
            $actions[] = Action::make('shippingLabelErrors')
                ->iconButton()
                ->color('danger')
                ->icon('heroicon-o-exclamation-triangle')
                ->label(__('Labels met fouten (:aantal)', ['aantal' => $failedCount]))
                ->tooltip(__('Labels met fouten (:aantal)', ['aantal' => $failedCount]))
                ->url(\Dashed\DashedEcommerceCore\Filament\Pages\ShippingLabelErrors::getUrl())
                ->openUrlInNewTab();
        }

        return array_merge($actions, ecommerce()->buttonActions('orders'));
    }

    /**
     * Tab definitions live in OrderListTabs (see the docblock there).
     */
    public function getTabs(): array
    {
        $tabs = [];

        foreach (OrderListTabs::labels() as $key => $label) {
            $tabs[$key] = Tab::make($label)
                ->modifyQueryUsing(fn (Builder $query): Builder => OrderListTabs::apply($key, $query))
                ->badge(fn (): int => $this->getTabCounts()[$key] ?? 0);
        }

        return $tabs;
    }

    /**
     * Two queries for all six counters: one aggregate over the filtered list
     * query, one for retouren (status filter bypassed, as on that tab).
     * Both start from the same base query as the table and apply the same
     * filters and search, only the tab itself is left out.
     *
     * @return array<string, int>
     */
    public function getTabCounts(): array
    {
        if ($this->tabCounts !== null) {
            return $this->tabCounts;
        }

        $counts = OrderListTabs::countFulfillmentTabs(
            $this->filterTableQuery(OrderResource::getEloquentQuery())
        );

        $this->countingReturns = true;

        try {
            $counts[OrderListTabs::RETURNS] = OrderListTabs::countReturns(
                $this->filterTableQuery(OrderResource::getEloquentQuery())
            );
        } finally {
            $this->countingReturns = false;
        }

        return $this->tabCounts = $counts;
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->tabCounts = null;
    }

    /**
     * The status filter defaults to paid statuses; on the retouren tab that
     * would hide every credit order, so its default is ignored there (see
     * OrderResource::statusFilterQuery(); a chosen status still applies).
     */
    public function ignoresStatusFilter(): bool
    {
        return $this->countingReturns || $this->activeTab === OrderListTabs::RETURNS;
    }

    protected function getFooterWidgets(): array
    {
        return [
            OrderWorkflowStatsWidget::class,
            OrderOutstandingStatsWidget::class,
        ];
    }
}
