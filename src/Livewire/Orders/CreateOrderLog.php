<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Closure;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Mail\OrderNoteMail;

class CreateOrderLog extends Component implements HasForms
{
    use WithFileUploads;
    use InteractsWithForms;

    public Order $order;
    public $publicForCustomer = false;
    public bool $sendEmailToCustomer = false;
    public string $emailSubject = 'Je bestelling is bijgewerkt';
    public array $images = [];
    public string $note = '';

    public function mount(Order $order)
    {
        $this->order = $order;
    }

    protected function getFormSchema(): array
    {
        return [
            Toggle::make('publicForCustomer')
                ->label('Zichtbaar voor klant')
                ->default(false)
                ->reactive(),
            Toggle::make('sendEmailToCustomer')
                ->label('Moet de klant een notificatie van deze notitie ontvangen?')
                ->default(false)
                ->visible(fn (Closure $get) => $get('publicForCustomer'))
                ->reactive(),
            TextInput::make('emailSubject')
                ->label('Onderwerp van de mail')
                ->visible(fn (Closure $get) => $get('publicForCustomer') && $get('sendEmailToCustomer')),
            FileUpload::make('images')
                ->name('Bestanden')
                ->multiple()
                ->enableDownload()
                ->enableOpen()
                ->enableReordering()
                ->acceptedFileTypes(['image/*', 'application/pdf'])
                    ->directory('dashed/orders/logs/images')
                ->maxSize(50000),
            Textarea::make('note')
                ->label('Notitie')
                ->placeholder('Typ hier je notitie')
                ->required()
                ->minLength(3)
                ->maxLength(1500)
                ->rows(3),
        ];
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.create-order-log');
    }

    public function submit()
    {
        $orderLog = new OrderLog();
        $orderLog->order_id = $this->order->id;
        $orderLog->user_id = Auth::user()->id;
        $orderLog->tag = 'order.note.created';
        $orderLog->note = $this->note;
        $orderLog->public_for_customer = $this->publicForCustomer;
        $orderLog->send_email_to_customer = $this->publicForCustomer && $this->sendEmailToCustomer;
        $orderLog->email_subject = $this->emailSubject;

        $images = [];
        foreach ($this->images ?: [] as $image) {
            $uploadedImage = $image->store('/dashed/orders/logs/images');
            $images[] = $uploadedImage;
        }

        $orderLog->images = $images;
        $orderLog->save();

        if ($orderLog->send_email_to_customer) {
            try {
                Mail::to($this->order->email)->send(new OrderNoteMail($this->order, $orderLog));
            } catch (\Exception $exception) {
            }
        }

        $this->emit('refreshPage');
        Notification::make()
            ->success()
            ->title('De notificatie is aangemaakt')
            ->send();

        $this->reset([
            'publicForCustomer',
            'sendEmailToCustomer',
            'emailSubject',
            'images',
            'note',
        ]);
    }
}
