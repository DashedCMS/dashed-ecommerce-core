<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns\CreateManualOrderActions;

class POSCustomerPage extends Component implements HasForms
{
    use CreateManualOrderActions;

    public $fullscreen = false;

    protected $listeners = [
        'fullscreenValue',
    ];

    public function mount(): void
    {
        $this->initialize('point-of-sale', 'pos');
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
