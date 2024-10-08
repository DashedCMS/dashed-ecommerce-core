<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Account;

use Livewire\Component;
use Illuminate\Database\Eloquent\Collection;

class Orders extends Component
{
    public Collection $orders;

    public function mount()
    {
        $this->orders = auth()->user()->orders()->get();
    }

    public function render()
    {
        return view(env('SITE_THEME', 'dashed') . '.account.orders');
    }
}
