<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class PaymentsList extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    /**
     * Wat er per betaling naar de provider ging en wat er terugkwam, zoals
     * vastgelegd bij het starten (OrderPayment::recordPspRequest()).
     */
    public function pspDataAction(): Action
    {
        return Action::make('pspData')
            ->label(__('Verzonden data'))
            ->icon('heroicon-o-code-bracket')
            ->link()
            ->modalHeading(__('Verzonden data'))
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Sluiten'))
            ->modalContent(function (array $arguments) {
                $orderPayment = OrderPayment::query()
                    ->where('order_id', $this->order->id)
                    ->findOrFail($arguments['payment'] ?? null);

                return view('dashed-ecommerce-core::orders.components.psp-data', [
                    'blocks' => [
                        ['title' => __('Verstuurd naar de provider'), 'json' => self::prettyJson($orderPayment->psp_request)],
                        ['title' => __('Antwoord'), 'json' => self::prettyJson($orderPayment->psp_response)],
                    ],
                    'labels' => [
                        'copy' => __('Kopiëren'),
                        'copied' => __('Gekopieerd'),
                        'empty' => __('Niet vastgelegd.'),
                    ],
                ]);
            });
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('Betaald'),
            'pending' => __('In afwachting'),
            'failed' => __('Mislukt'),
            'cancelled' => __('Geannuleerd'),
            default => (string) $status,
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'pending' => 'warning',
            'failed', 'cancelled' => 'danger',
            default => 'gray',
        };
    }

    protected static function prettyJson(?array $data): ?string
    {
        return filled($data)
            ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.order-payments');
    }
}
