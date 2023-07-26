<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Qubiqx\QcommerceCore\Classes\Sites;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Database\Eloquent\Collection;
use Qubiqx\QcommerceCore\Mail\PasswordResetMail;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class ForgotPassword extends Component
{
    public ?string $email = '';

    public function submit()
    {
        $this->validate([
            'email' => [
                'required',
                'email:rfc',
                'max:255',
            ]
        ]);

        $user = User::where('email', $this->email)->first();
        if ($user) {
            $user->password_reset_token = Str::random(64);
            $user->password_reset_requested = Carbon::now();
            $user->save();
            Mail::to($user->email)->send(new PasswordResetMail($user));
        }

        $this->emit('showAlert', 'success', Translation::get('forgot-password-post-success', 'login', 'If we can find an account with your email you will receive a email to reset your password.'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.forgot-password');
    }
}
