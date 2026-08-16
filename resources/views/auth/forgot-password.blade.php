<x-guest-layout title="Reset password">
    <h1 class="text-lg font-semibold text-slate-900 mb-2">Reset password</h1>
    <p class="text-sm text-slate-600 mb-6">
        {{ __('We will email you a link to set a new password.') }}
    </p>

    <x-auth-session-status class="mb-4 p-3 rounded-md bg-orange-50 border border-orange-100 text-sm text-orange-900" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-700" />
            <x-text-input id="email" class="block mt-1 w-full border-slate-300" type="email" name="email"
                :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center normal-case tracking-normal">
            {{ __('Send reset link') }}
        </x-primary-button>

        <p class="text-center text-sm">
            <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700">{{ __('Back to sign in') }}</a>
        </p>
    </form>
</x-guest-layout>
