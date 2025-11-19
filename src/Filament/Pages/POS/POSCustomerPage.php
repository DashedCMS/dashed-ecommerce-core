<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\POSCart;

class POSCustomerPage extends Component
{
    public $cartInstance = 'handorder';
    public $orderOrigin = 'pos';

    public ?array $products = [];
    public bool $fullscreen = false;

    public $total;
    public $subtotal;
    public $discount;
    public $activeDiscountCode;
    public $vat;
    public array $vatPercentages = [];

    public function mount(): void
    {
        //        $this->getProducts();
    }

    //    public function getProducts(): void
    //    {
    //        $posCart = POSCart::where('user_id', auth()->user()->id)->where('status', 'active')->first();
    //        if ($posCart) {
    //            ray('Retrieving POS cart for user ' . auth()->user()->id);
    //            $response = Http::post(route('api.point-of-sale.retrieve-cart'), [
    //                'cartInstance' => $this->cartInstance,
    //                'posIdentifier' => $posCart->identifier,
    //                'discountCode' => $posCart->discount_code,
    //            ])
    //            ->json();
    //
    //            $this->products = $response['products'];
    //            $this->subtotal = $response['subTotal'];
    //            $this->total = $response['total'];
    //            $this->discount = $response['discount'];
    //            $this->vat = $response['vat'];
    //            $this->activeDiscountCode = $response['activeDiscountCode'];
    //            $this->vatPercentages = $response['vatPercentages'];
    //        } else {
    //            ray('No active POS cart for user ' . auth()->user()->id);
    //            $this->products = [];
    //            $this->subtotal = 0;
    //            $this->total = 0;
    //            $this->discount = 0;
    //            $this->vat = 0;
    //            $this->activeDiscountCode = null;
    //            $this->vatPercentages = [];
    //        }
    //    }

    public function notify($type, $message): void
    {
        Notification::make()
            ->title($message)
            ->$type()
            ->send();
    }

    public function render()
    {
        return view('dashed-ecommerce-core::pos.pages.customer-point-of-sale');
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
