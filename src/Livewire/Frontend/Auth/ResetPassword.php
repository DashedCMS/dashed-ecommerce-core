<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceTranslations\Models\Translation;

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
                'required_with:passwordConfirmation',
                'same:passwordConfirmation',
            ],
        ]);

        $this->user->password_reset_token = null;
        $this->user->password_reset_requested = null;
        $this->user->password = Hash::make($this->password);
        $this->user->save();

        Auth::login($this->user);

        return redirect(route('qcommerce.frontend.account'))->with('success', Translation::get('reset-password-post-success', 'login', 'Your password has been reset!'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.reset-password');
    }
}
