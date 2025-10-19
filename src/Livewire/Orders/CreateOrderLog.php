<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Components\Utilities\Get;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;
use Filament\Actions\Concerns\InteractsWithActions;
<<<<<<< HEAD
use Filament\Schemas\Concerns\InteractsWithSchemas;
=======
use Dashed\DashedEcommerceCore\Models\OrderLogTemplate;
use Dashed\DashedEcommerceCore\Classes\OrderVariableReplacer;
>>>>>>> fb4555ce42557585ae0976d428f4262d50f93752

class CreateOrderLog extends Component implements HasSchemas, HasActions
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
        $actions = [];

        foreach (OrderLogTemplate::all() as $template) {
            $actions[] = Action::make('template-' . $template->id)
                ->label('Verstuur ' . $template->name)
                ->color('warning')
                ->action(function ($data, $action) use ($template) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $this->order->id;
                    $orderLog->user_id = Auth::user()->id;
                    $orderLog->tag = 'order.note.created';
                    $orderLog->note = OrderVariableReplacer::handle($this->order, $template->body);
                    $orderLog->public_for_customer = 1;
                    $orderLog->send_email_to_customer = 1;
                    $orderLog->email_subject = OrderVariableReplacer::handle($this->order, $template->subject);
                    $orderLog->images = [];
                    $orderLog->save();

                    try {
                        Mail::to($this->order->email)->send(new OrderNoteMail($this->order, $orderLog));
                    } catch (\Exception $exception) {
                    }

                    Notification::make()
                        ->success()
                        ->title('De template ' . $template->name . ' is verstuurd')
                        ->send();

                    $this->dispatch('refreshData');
                    //                    $this->closeActionModal();
                });
        }

        return Action::make('action')
            ->label('Maak bestellings notitie')
            ->color('primary')
            ->extraModalFooterActions($actions)
            ->fillForm(function ($record) {
                return [
                    'emailSubject' => 'Je bestelling is bijgewerkt',
                ];
            })
            ->schema([
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
                    ->helperText(fn (Get $get) => $get('publicForCustomer') && $get('sendEmailToCustomer') ? 'Aanhef en afsluiting zit er standaard bij' : '')
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
