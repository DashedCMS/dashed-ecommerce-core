<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Livewire\Component;
use Livewire\WithFileUploads;
use Qubiqx\QcommerceCore\Classes\Sites;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Database\Eloquent\Collection;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

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
