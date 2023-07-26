<x-container>
    <div class="w-full max-w-screen-xl px-4 mx-auto sm:px-8">
        <div class="py-0 sm:py-8 mx-auto grid grid-cols-1 max-w-xl">
            <form wire:submit.prevent="submit" class="space-y-6 flex flex-col mt-auto">
                <h2 class="font-display font-bold text-xl">{{Translation::get('forgot-password', 'login', 'Forgot password?')}}</h2>
                <x-fields.input required type="email" model="email" id="email" class="w-full" :label="Translation::get('email', 'login', 'E-mail')" :helperText="Translation::get('forgot-password-description', 'login', 'Enter your email and we mail you to reset your password.')" />
                <button class="button button-white-on-primary mt-auto">
                    {{Translation::get('request-password-reset', 'login', 'Request password reset')}}
                </button>
            </form>
            <div class="mt-4 text-center">
                <a class="text-primary-500" href="{{AccountHelper::getAccountUrl()}}">
                    {{Translation::get('login', 'login', 'Login')}}
                </a>
            </div>
        </div>
    </div>
</x-container>
