<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Filament\Forms\Get;
use Livewire\Component;
use Filament\Actions\Action;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;
use Filament\Actions\Concerns\InteractsWithActions;

class CreateOrderLog extends Component implements HasForms, HasActions
{
    use WithFileUploads;
    use InteractsWithForms;
    use InteractsWithActions;

    public Order $order;

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label('Maak bestellings notitie')
            ->color('primary')
            ->fillForm(function ($record) {
                return [
                    'emailSubject' => 'Je bestelling is bijgewerkt',
                ];
            })
            ->form([
                Toggle::make('publicForCustomer')
                    ->label('Zichtbaar voor klant')
                    ->default(false)
                    ->reactive(),
                Toggle::make('sendEmailToCustomer')
                    ->label('Moet de klant een notificatie van deze notitie ontvangen?')
                    ->default(false)
                    ->visible(fn (Get $get) => $get('publicForCustomer'))
                    ->reactive(),
                TextInput::make('emailSubject')
                    ->label('Onderwerp van de mail')
                    ->visible(fn (Get $get) => $get('publicForCustomer') && $get('sendEmailToCustomer')),
                mediaHelper()->field('images', 'Bestanden', multiple: true),
                Textarea::make('note')
                    ->label('Notitie')
                    ->placeholder('Typ hier je notitie')
                    ->required()
                    ->minLength(3)
                    ->maxLength(1500)
                    ->rows(3),
            ])
            ->action(function ($data) {
                $orderLog = new OrderLog();
                $orderLog->order_id = $this->order->id;
                $orderLog->user_id = Auth::user()->id;
                $orderLog->tag = 'order.note.created';
                $orderLog->note = $data['note'];
                $orderLog->public_for_customer = $data['publicForCustomer'];
                $orderLog->send_email_to_customer = $data['publicForCustomer'] && $data['sendEmailToCustomer'];
                $orderLog->email_subject = $data['emailSubject'] ?? 'Je bestelling is bijgewerkt';

                $orderLog->images = $data['images'];
                $orderLog->save();

                if ($orderLog->send_email_to_customer) {
                    try {
                        Mail::to($this->order->email)->send(new OrderNoteMail($this->order, $orderLog));
                    } catch (\Exception $exception) {
                    }
                }

                Notification::make()
                    ->success()
                    ->title('De notitie is aangemaakt')
                    ->send();

                $this->dispatch('refreshData');
            });
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
