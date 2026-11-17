<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblem;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblemFixer;
use Dashed\DashedEcommerceCore\Services\Shipping\LabelProblems\LabelProblemDetector;

class LabelProblemBanner extends Component
{
    public Order $order;

    public function mount($order): void
    {
        $this->order = $order;
    }

    public function getProblemProperty(): ?LabelProblem
    {
        return LabelProblemDetector::forOrder($this->order->fresh() ?? $this->order);
    }

    public function fix(): void
    {
        $problem = $this->problem;

        if (! $problem || ! $problem->suggestion) {
            Notification::make()
                ->title(__('Er is geen voorstel om automatisch te herstellen'))
                ->warning()
                ->send();

            return;
        }

        $result = app(LabelProblemFixer::class)->apply($this->order->fresh() ?? $this->order, $problem, Auth::id());

        Notification::make()
            ->title($result['message'])
            ->{$result['ok'] ? 'success' : 'danger'}()
            ->send();

        $this->order = $this->order->fresh() ?? $this->order;
        $this->dispatch('refreshData');
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.label-problem-banner', [
            'problem' => $this->problem,
        ]);
    }
}
