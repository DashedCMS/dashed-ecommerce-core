<?php

namespace Qubiqx\QcommerceEcommerceCore\Livewire\Frontend\Auth;

use Carbon\Carbon;
use Livewire\Component;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Qubiqx\QcommerceCore\Models\User;
use Qubiqx\QcommerceCore\Mail\PasswordResetMail;
use Qubiqx\QcommerceTranslations\Models\Translation;

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
            ],
        ]);

        $user = User::where('email', $this->email)->first();
        if ($user) {
            $user->password_reset_token = Str::random(64);
            $user->password_reset_requested = Carbon::now();
            $user->save();
            Mail::to($user->email)->send(new PasswordResetMail($user));
        }

        $this->reset('email');

        $this->emit('showAlert', 'success', Translation::get('forgot-password-post-success', 'login', 'If we can find an account with your email you will receive a email to reset your password.'));
    }

    public function render()
    {
        return view('qcommerce-ecommerce-core::frontend.auth.forgot-password');
    }
}
