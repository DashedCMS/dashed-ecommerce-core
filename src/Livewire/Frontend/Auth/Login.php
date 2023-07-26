<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Livewire\Component;

class Login extends Component
{
    public ?string $email;
    public ?string $emailConfirmation;
    public ?string $password;

    public function mount()
    {
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.login');
    }
}
