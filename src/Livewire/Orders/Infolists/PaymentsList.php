<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
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

    public function infolist(Schema $schema): Schema
    {
        $paymentsSchema = [];

        foreach ($this->order->orderPayments as $orderPayment) {
            $pid = $orderPayment->id ?? spl_object_id($orderPayment);

            $paymentsSchema[] = Fieldset::make('payment_' . $pid)
                ->label(__('Betaling van :datum', ['datum' => $orderPayment->created_at->format('d-m-Y H:i')]))
                ->schema([
                    TextEntry::make('psp_' . $pid)
                        ->label(__('PSP'))
                        ->state(fn () => $orderPayment->psp ?: '-'),

                    TextEntry::make('psp_id_' . $pid)
                        ->label(__('PSP ID'))
                        ->state(fn () => $orderPayment->psp_id ?: '-'),

                    TextEntry::make('payment_method_' . $pid)
                        ->label(__('Betaalmethode'))
                        ->state(fn () => $orderPayment->payment_method ?: ($orderPayment->paymentMethod->name ?? '-')),

                    TextEntry::make('amount_' . $pid)
                        ->label(__('Bedrag'))
                        ->state(fn () => $orderPayment->amount)
                        ->money('EUR'),

                    TextEntry::make('status_' . $pid)
                        ->label(__('Status'))
                        ->state(fn () => $orderPayment->status)
                        ->badge()
                        ->color(fn () => match ($orderPayment->status) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed', 'cancelled' => 'danger',
                            default => 'gray',
                        }),

                    TextEntry::make('note_' . $pid)
                        ->label(__('Notitie'))
                        ->state(fn () => data_get($orderPayment->getAttribute('attributes'), 'note') ?: '-')
                        ->columnSpanFull()
                        ->visible(fn () => filled(data_get($orderPayment->getAttribute('attributes'), 'note'))),

                    Actions::make([
                        Action::make('openPspData_' . $pid)
                            ->label(__('Verzonden data'))
                            ->icon('heroicon-o-code-bracket')
                            ->link()
                            ->alpineClickHandler("\$wire.mountAction('pspData', { payment: {$orderPayment->id} })"),
                    ])
                        ->columnSpanFull()
                        ->visible(fn () => filled($orderPayment->psp_request) || filled($orderPayment->psp_response)),
                ])
                ->columns(3)
                ->columnSpanFull();
        }

        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make(__('payments_root'))
                    ->label(__('Betalingen'))
                    ->schema($paymentsSchema)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Wat er per betaling naar de provider ging en wat er terugkwam, zoals
     * vastgelegd bij het starten (OrderPayment::recordPspRequest()).
     */
    public function pspDataAction(): Action
    {
        return Action::make('pspData')
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

    protected static function prettyJson(?array $data): ?string
    {
        return filled($data)
            ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
