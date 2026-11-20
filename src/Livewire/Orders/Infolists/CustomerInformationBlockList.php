<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Helper;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CustomerHistory;

class CustomerInformationBlockList extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Order $order;

    private ?CustomerHistory $cachedHistory = null;

    protected function history(): CustomerHistory
    {
        return $this->cachedHistory ??= new CustomerHistory($this->order);
    }

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order): void
    {
        $this->order = $order;
    }

    /**
     * Tekst over eerdere bestellingen van deze klant, of null als de klant
     * niet te herkennen is (geen gebruiker, e-mail of naam).
     */
    public function historyText(): ?string
    {
        if ($this->history()->matchKey() === null) {
            return null;
        }

        $other = $this->history()->otherCount();

        return $other === 0
            ? __('Dit is de eerste bestelling van deze klant')
            : __('Deze klant heeft al :aantal andere bestelling(en)', ['aantal' => $other]);
    }

    public function customerHistoryAction(): Action
    {
        return Action::make('customer_history')
            ->label(__('Bekijk details'))
            ->icon('heroicon-o-chart-bar')
            ->link()
            ->modalHeading(fn () => __('Bestelhistorie: :naam', ['naam' => $this->order->name ?: $this->order->email]))
            ->modalWidth('5xl')
            ->modalContent(fn () => view(
                'dashed-ecommerce-core::filament.orders.customer-history-modal',
                ['history' => $this->history()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'))
            ->visible(fn () => $this->history()->matchKey() !== null && $this->history()->otherCount() > 0);
    }

    public function profilePicture(): ?string
    {
        return $this->order->email ? Helper::getProfilePicture($this->order->email) : null;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.customer-card');
    }
}
