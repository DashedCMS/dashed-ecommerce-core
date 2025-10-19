<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Livewire\Component;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns\CreateManualOrderActions;

class POSPage2 extends Component implements HasSchemas
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
        return view('dashed-ecommerce-core::pos.pages.point-of-sale');
    }

    public function fullscreenValue($fullscreen)
    {
        $this->fullscreen = $fullscreen;
    }
}
