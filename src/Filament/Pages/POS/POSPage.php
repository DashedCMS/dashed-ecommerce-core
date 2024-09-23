<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns\CreateManualOrderActions;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;
use Livewire\Component;

class POSPage extends Component
{
    use CreateManualOrderActions;

    public $fullscreen = false;

    protected $listeners = [
        'fullscreenValue',
    ];

    public function mount(): void
    {
        $this->initialize('point-of-sale');
    }

    public function render()
    {
        return view('dashed-ecommerce-core::pos.pages.point-of-sale');
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
