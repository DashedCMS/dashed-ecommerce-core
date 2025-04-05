<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders;

use Livewire\Component;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Actions\Contracts\HasActions;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Concerns\InteractsWithActions;
use Dashed\DashedEcommerceCore\Models\FulfillmentCompany;

/**
 * Handles sending orders to fulfillment companies and manages the associated operations.
 */
class SendOrderToFulfillmentCompanies extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public Order $order;
    public Collection $orderProductsToSent;
    public bool $isPos = false;
    public ?string $buttonText = '';
    public ?string $buttonClass = '';

    /**
     * Initializes the component with the provided order and optional parameters.
     *
     * @param Order $order The order object to be assigned.
     * @param bool $isPos Indicates whether the order is processed as POS (Point of Sale). Default is false.
     * @param string|null $buttonText The text to be used for the button. Default is an empty string.
     * @param string|null $buttonClass The CSS class to be applied to the button. Default is an empty string.
     *
     * @return void
     */
    public function mount(Order $order, bool $isPos = false, ?string $buttonText = '', ?string $buttonClass = '')
    {
        $this->order = $order;
        $this->isPos = $isPos;
        $this->buttonText = $buttonText ?: 'Stuur naar fulfilment partijen';
        $this->buttonClass = $buttonClass;
        $orderProducts = $this->order->orderProducts;
        foreach ($orderProducts as $key => $orderProduct) {
            if (! $orderProduct->fulfillmentCompany) {
                unset($orderProducts[$key]);
            }
        }
        $this->orderProducts = $orderProducts;
        $this->order->refresh(); //Otherwise orderProducts is empty for other livewire components, idk why
    }

    public function action(): Action
    {
        return Action::make('action')
            ->label($this->buttonText)
            ->extraAttributes(['class' => $this->buttonClass])
            ->color('primary')
            ->visible($this->orderProducts->isNotEmpty())
            ->fillForm($this->getInitialFormData())
            ->form($this->getFormSchema())
            ->action($this->handleAction(...));
    }

    private function getInitialFormData(): array
    {
        //        $fillForm = [
        //            'sendProductsToCustomer' => true,
        //        ];

        foreach ($this->order->orderProducts()->orderBy('fulfillment_provider')->get() as $orderProduct) {
            $fillForm["order_product_{$orderProduct->id}_send_to_fulfiller"] = ! $orderProduct->send_to_fulfiller;
        }

        return $fillForm;
    }

    private function getFormSchema(): array
    {
        return $this->getOrderProductsSection();

        return array_merge($this->getOrderProductsSection(), [
            $this->getOtherOptionsSection(),
        ]);
    }

    //    private function getOtherOptionsSection(): Section
    //    {
    //        return Section::make('Overige opties')
    //            ->schema([
    //                Toggle::make('sendProductsToCustomer')
    //                    ->label('Verstuur producten naar de klant'),
    //                mediaHelper()->field('files', 'Bijlagen', multiple: true),
    //            ])
    //            ->columns(['default' => 1, 'lg' => 2]);
    //    }

    private function getOrderProductsSection(): array
    {
        $sections = [];

        foreach (FulfillmentCompany::all() as $fulfillmentCompany) {
            if ($this->orderProducts->where('fulfillment_provider', $fulfillmentCompany->id)->count()) {
                $sections[] = Section::make('Bestelde producten voor ' . $fulfillmentCompany->name)
                    ->schema(
                        array_merge(
                            $this->getOrderProductSchema($fulfillmentCompany),
                            [
                                Toggle::make('sendProductsToCustomer_' . $fulfillmentCompany->id)
                                    ->label('Verstuur producten naar de klant'),
                                mediaHelper()->field('files_' . $fulfillmentCompany->id, 'Bijlagen', multiple: true, defaultFolder: 'orders/' . $this->order->invoice_id),
                            ]
                        )
                    )
                    ->columns(['default' => 1, 'lg' => 2])
                    ->hidden(! in_array($this->order->order_origin, ['own', 'pos']));
            }
        }

        return $sections;
    }

    private function getOrderProductSchema(FulfillmentCompany $fulfillmentCompany): array
    {
        return $this->orderProducts->where('fulfillment_provider', $fulfillmentCompany->id)->map(function ($orderProduct) {
            return Section::make()
                ->label($orderProduct->name)
                ->schema([
                    Toggle::make("order_product_{$orderProduct->id}_send_to_fulfiller")
                        ->label("{$orderProduct->name} {$orderProduct->quantity}x versturen")
                        ->helperText($orderProduct->send_to_fulfiller ? 'Dit product is al doorgestuurd naar de fulfilment partij.' : 'Dit product moet nog doorgestuurd worden naar de fulfilment partij.'),
                ]);
        })->toArray();
    }

    private function handleAction(array $data): void
    {
        $fulfillmentCompanies = [];

        $hasOrderProductSelected = false;
        foreach ($this->orderProducts as $orderProduct) {
            if ($data["order_product_{$orderProduct->id}_send_to_fulfiller"]) {
                $hasOrderProductSelected = true;
                $fulfillmentCompanies[$orderProduct->fulfillment_provider][] = $orderProduct;
            }
        }

        if (! $hasOrderProductSelected) {
            Notification::make()
                ->title('Geen producten geselecteerd')
                ->body('Selecteer minimaal één product om door te sturen naar de fulfilment partij.')
                ->danger()
                ->send();

            return;
        }

        foreach ($fulfillmentCompanies as $fulfillmentProvider => $orderProducts) {
            $fulfillmentCompany = FulfillmentCompany::find($fulfillmentProvider);
            $fulfillmentCompany->sendOrder($this->order, $orderProducts, $data['sendProductsToCustomer_' . $fulfillmentCompany->id], $data['files_' . $fulfillmentCompany->id]);
        }

        Notification::make()
            ->title('Bestelling doorgestuurd')
            ->body('De bestelling is doorgestuurd naar de fulfilment partijen.')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.plain-action');
    }
}
