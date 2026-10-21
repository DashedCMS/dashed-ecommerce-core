<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Maatwebsite\Excel\Facades\Excel;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\PurchaseAdvice;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Exports\PurchaseAdviceExport;

/**
 * Welke producten binnenkort op zijn en hoeveel er bij moet, uit de
 * verkoopsnelheid van de afgelopen periode (PurchaseAdvice). Alleen lezen;
 * de horizon wordt per site onthouden.
 */
class PurchaseAdvicePage extends Page
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?string $title = 'Inkoopadvies';

    protected static ?string $slug = 'inkoopadvies';

    protected static ?int $navigationSort = 90;

    protected string $view = 'dashed-ecommerce-core::filament.pages.purchase-advice';

    public int $days = PurchaseAdvice::DEFAULT_PERIOD;

    public int $horizon = PurchaseAdvice::DEFAULT_HORIZON;

    public ?int $categoryId = null;

    public bool $onlyWithinHorizon = true;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_product');
    }

    public function mount(): void
    {
        $this->horizon = PurchaseAdvice::horizonFor();
    }

    public function updatedDays(): void
    {
        if (! in_array($this->days, PurchaseAdvice::PERIODS, true)) {
            $this->days = PurchaseAdvice::DEFAULT_PERIOD;
        }
    }

    public function updatedHorizon(): void
    {
        $this->horizon = max(1, min(365, (int) $this->horizon));
        Customsetting::set('purchase_advice_horizon_days', (string) $this->horizon, Sites::getActive());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return PurchaseAdvice::rows($this->days, $this->horizon, $this->categoryId ?: null, $this->onlyWithinHorizon);
    }

    /**
     * @return array<int, string>
     */
    public function categoryOptions(): array
    {
        return ProductCategory::query()
            ->thisSite()
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (ProductCategory $category) => [$category->id => (string) $category->name])
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Exporteren naar Excel'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(
                    new PurchaseAdviceExport($this->rows()),
                    __('Inkoopadvies') . ' ' . now()->format('Y-m-d') . '.xlsx',
                )),
        ];
    }
}
