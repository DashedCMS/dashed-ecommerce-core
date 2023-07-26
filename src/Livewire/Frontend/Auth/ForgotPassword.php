<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithFileUploads;
use Qubiqx\QcommerceCore\Classes\Sites;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Database\Eloquent\Collection;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceEcommerceCore\Livewire\Concerns\CartActions;

class ForgotPassword extends Component
{
    public ?string $loginEmail = '';
    public ?string $loginPassword = '';
    public ?bool $loginRememberMe = false;
    public ?string $registerEmail = '';
    public ?string $registerPassword = '';
    public ?string $registerPasswordConfirmation = '';
    public ?bool $registerRememberMe = false;

    public function login()
    {
        $this->validate([
            'loginEmail' => [
                'required',
                'email',
                'min:3',
                'max:255',
            ],
            'loginPassword' => [
                'required',
                'min:6',
                'max:255',
            ],
        ], [],
            [
                'loginEmail' => Translation::get('email', 'validation-attributes', 'email'),
                'loginPassword' => Translation::get('password', 'validation - attributes', 'password'),
            ]);

        $user = User::where('email', $this->loginEmail)->first();

        if (!$user) {
            return redirect()->back()->with('error', Translation::get('no-user-found', 'login', 'We could not find a user matching these criteria'));
        }

        if (!Hash::check($this->loginPassword, $user->password)) {
            return redirect()->back()->with('error', Translation::get('no-user-found', 'login', 'We could not find a user matching these criteria'));
        }

        Auth::login($user, $this->loginRememberMe);

        if (ShoppingCart::cartItemsCount() > 0) {
            return redirect(ShoppingCart::getCartUrl())->with('success', Translation::get('succesfully-logged-in', 'login', 'You are logged in!'));
        } else {
            return redirect(route('qcommerce.frontend.account'))->with('success', Translation::get('succesfully-logged-in', 'login', 'You are logged in!'));
        }
    }

    public function register()
    {
        $this->validate([
            'registerEmail' => [
                'unique:users,email',
                'required',
                'email:rfc',
                'max:255',
            ],
            'registerPassword' => [
                'min:6',
                'max:255',
                'required_with:registerPasswordConfirmation',
                'same:registerPasswordConfirmation',
            ],
        ], [],
            [
                'registerEmail' => Translation::get('email', 'validation-attributes', 'email'),
                'registerPassword' => Translation::get('password', 'validation - attributes', 'password'),
                'registerPasswordConfirmation' => Translation::get('password-confirmation', 'validation - attributes', 'password confirmation'),
            ]);

        $user = new User();
        $user->email = $this->registerEmail;
        $user->password = Hash::make($this->registerPassword);
        $user->save();

        Auth::login($user, $this->registerRememberMe);

        return redirect(route('qcommerce.frontend.account'))->with('success', Translation::get('succesfully-logged-in', 'login', 'You are logged in!'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.forgot-password');
    }
}
