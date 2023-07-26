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

class ResetPassword extends Component
{
    public User $user;
    public ?string $password = '';
    public ?string $passwordConfirmation = '';

    public function mount(string$passwordResetToken)
    {
        $this->user = User::where('password_reset_token', $passwordResetToken)->first();
        if (! $this->user) {
            abort(404);
        }
    }

    public function submit()
    {
        $this->validate([
            'password' => [
                'min:6',
                'max:255',
                'confirmed',
                'required_with:passwordConfirmation',
                'same:passwordConfirmation',
            ],
        ]);

        $this->user->password_reset_token = null;
        $this->user->password_reset_requested = null;
        $this->user->password = Hash::make($this->password);
        $this->user->save();

        Auth::login($user);

        $this->emit('showAlert', 'success', Translation::get('reset-password-post-success', 'login', 'Your password has been reset!'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.reset-password');
    }
}
