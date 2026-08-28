<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class CreateTrackAndTrace extends Component implements HasSchemas, HasActions
{
    use WithFileUploads;
    use InteractsWithSchemas;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label(__('Voeg track and trace toe'))
            ->color('primary')
            ->schema([
                TextInput::make('delivery_company')
                    ->label(__('Vervoersbedrijf'))
                    ->required(),
                TextInput::make('code')
                    ->label(__('Track and trace code'))
                    ->required(),
                TextInput::make('link')
                    ->label(__('Link'))
                    ->required(),
                Toggle::make('mail_customer')
                    ->label(__('Klant mailen over deze track & trace'))
                    ->helperText(__('Standaard volgt dit de instelling bij Instellingen, Bestellingen, Fulfillment notificaties.'))
                    ->default(fn () => (bool) Customsetting::get('track_and_trace_mail_enabled', null, '1', $this->order->locale)),
            ])
            ->action(function ($data) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $this->order->id;
                $orderLog->user_id = Auth::user()->id;
                $orderLog->tag = 'order.track-and-trace.created';
                $orderLog->public_for_customer = 0;
                $orderLog->send_email_to_customer = 0;
                $orderLog->save();

                $trackAndTrace = $this->order->trackAndTraces()->make([
                    'supplier' => 'Handmatig',
                    'delivery_company' => $data['delivery_company'],
                    'code' => $data['code'],
                    'url' => $data['link'],
                ]);
                $trackAndTrace->mailCustomer = array_key_exists('mail_customer', $data) ? (bool) $data['mail_customer'] : null;
                $trackAndTrace->save();

                Notification::make()
                    ->success()
                    ->title(__('De track and trace is aangemaakt'))
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
