<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Livewire\Component;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns\CreateManualOrderActions;

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
