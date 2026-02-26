<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Account;

use Livewire\Component;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\AccountHelper;
use Illuminate\Database\Eloquent\Collection;

class Orders extends Component
{
    public Collection $orders;
    public ?User $user;

    public function mount()
    {
        if (auth()->guest()) {
            return redirect(AccountHelper::getLoginUrl());
        }

        $this->orders = auth()->user()->orders()->get();
        $this->user = auth()->user();

    }

    public function render()
    {
        return view(config('dashed-core.site_theme', 'dashed') . '.account.orders');
    }
}
