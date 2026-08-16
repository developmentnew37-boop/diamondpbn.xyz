<x-guest-layout title="Sign in">
    <h1 class="text-lg font-semibold text-slate-900 mb-6">Sign in</h1>

    <x-auth-session-status class="mb-4 p-3 rounded-md bg-orange-50 border border-orange-100 text-sm text-orange-900" :status="session('status')" />
    @if (session('info'))
        <div class="mb-4 p-3 rounded-md bg-slate-50 border border-slate-200 text-sm text-slate-700">{{ session('info') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded-md bg-red-50 border border-red-100 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-700" />
            <x-text-input id="email" class="block mt-1 w-full border-slate-300" type="email" name="email"
                :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between gap-2">
                <x-input-label for="password" :value="__('Password')" class="text-slate-700" />
                @if (Route::has('password.request'))
                    <a class="text-sm text-orange-600 hover:text-orange-700" href="{{ route('password.request') }}">
                        {{ __('Forgot password?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="block mt-1 w-full border-slate-300" type="password" name="password"
                required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 cursor-pointer">
            <input id="remember_me" type="checkbox"
                class="rounded border-slate-300 text-orange-500 focus:ring-orange-500 w-4 h-4" name="remember">
            <span class="text-sm text-slate-600">{{ __('Remember me') }}</span>
        </label>

        <x-primary-button class="w-full justify-center normal-case tracking-normal mt-2">
            {{ __('Sign in') }}
        </x-primary-button>

        @if (\App\Models\User::count() === 0)
            <p class="text-sm text-slate-600 text-center pt-2">
                {{ __('No account yet?') }}
                <a class="text-orange-600 hover:text-orange-700 font-medium" href="{{ route('register') }}">{{ __('Register') }}</a>
            </p>
        @endif
    </form>

    <p class="text-xs text-slate-500 text-center mt-5 leading-relaxed">
        A verification code will be emailed to you after sign-in.
    </p>
</x-guest-layout>
