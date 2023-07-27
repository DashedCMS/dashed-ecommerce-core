<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Account;

use Exception;
use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Qubiqx\QcommerceCore\Models\User;
use Illuminate\Support\Facades\Validator;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\OrderLog;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class Orders extends Component
{
    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.account.orders');
    }
}
