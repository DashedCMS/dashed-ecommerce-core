<x-master>
    <div class="bg-white py-16 sm:py-24">
        <x-container>
            <div class="py-0 sm:py-8 mx-auto grid grid-cols-1 lg:grid-cols-2 gap-8">
                <form class="flex flex-col space-y-6" method="post" action="{{AccountHelper::getRegisterPostUrl()}}">
                    @csrf
                    <h2 class="font-display font-bold text-xl">{{Translation::get('register', 'login', 'Registreren')}}</h2>
                    <div class="space-y-0 sm:space-y-1">
                        <label class="inline-block" for="">{{Translation::get('email', 'login', 'E-mail')}}</label>
                        <input type="email" id="email" name="email" required="" class="form-input w-full block">
                    </div>
                    <div class="space-y-0 sm:space-y-1">
                        <label class="inline-block"
                               for="">{{Translation::get('password', 'login', 'Wachtwoord')}}</label>
                        <input type="password" required="" id="password" name="password"
                               class="form-input w-full block">
                    </div>
                    <div class="space-y-0 sm:space-y-1">
                        <label class="inline-block"
                               for="">{{Translation::get('repeat-password', 'login', 'Wachtwoord herhalen')}}</label>
                        <input type="password" required="" id="password_confirmation" name="password_confirmation"
                               class="form-input w-full block">
                    </div>
                    <div class="flex items-center space-x-2">
                        <input id="remember_me_register" name="remember_me"
                               class="rounded-none form-checkbox" type="checkbox">
                        <label class="text-sm font-medium"
                               for="remember_me_register">{{Translation::get('remember-me', 'login', 'Remember me')}}</label>
                    </div>
                    <button class="button button-white-on-primary w-full">{{Translation::get('register', 'login', 'Registreren')}}</button>
                </form>
                <form method="post" action="{{AccountHelper::getLoginPostUrl()}}"
                      class="space-y-6 flex flex-col mt-auto">
                    @csrf
                    <h2 class="font-display font-bold text-xl">{{Translation::get('login', 'login', 'Inloggen')}}</h2>
                    <div class="space-y-0 sm:space-y-1">
                        <label class="inline-block" for="">{{Translation::get('email', 'login', 'E-mail')}}</label>
                        <input type="email" class="form-input w-full block" required="" id="email" name="email"
                               value="">
                    </div>
                    <div class="space-y-0 sm:space-y-1">
                        <label class="inline-block"
                               for="">{{Translation::get('password', 'login', 'Wachtwoord')}}</label>
                        <input type="password" class="form-input w-full block" id="password" name="password"
                               required="">
                    </div>
                    <div class="flex items-center space-x-2">
                        <input id="remember_me" name="remember_me"
                               class="rounded-none form-checkbox" type="checkbox">
                        <label class="text-sm font-medium"
                               for="remember_me">{{Translation::get('remember-me', 'login', 'Remember me')}}</label>
                    </div>
                    <button class="button button-white-on-primary mt-auto">{{Translation::get('login', 'login', 'Inloggen')}}</button>
                </form>
                <div class="lg:col-span-2 ml-auto -mt-4">
                    <a class="text-primary-500"
                       href="{{AccountHelper::getForgotPasswordUrl()}}">{{Translation::get('forgot-password', 'login', 'Wachtwoord vergeten?')}}</a>
                </div>
            </div>
        </x-container>
    </div>
</x-master>
