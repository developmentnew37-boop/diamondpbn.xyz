<x-guest-layout title="Verify email">
    <h1 class="text-lg font-semibold text-slate-900 mb-2">Verify email</h1>
    <p class="text-sm text-slate-600 mb-6">
        Enter the 6-digit code sent to <strong class="font-medium text-slate-800">{{ $email }}</strong>.
    </p>

    <x-auth-session-status class="mb-4 p-3 rounded-md bg-orange-50 border border-orange-100 text-sm text-orange-900" :status="session('status')" />
    @if (session('info'))
        <div class="mb-4 p-3 rounded-md bg-slate-50 border border-slate-200 text-sm text-slate-700">{{ session('info') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 rounded-md bg-red-50 border border-red-100 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login.otp.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Code')" class="text-slate-700" />
            <x-text-input id="code"
                class="block mt-1 w-full text-center text-lg tracking-widest font-mono border-slate-300"
                type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                autocomplete="one-time-code" placeholder="000000" />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
            <p class="text-xs text-slate-500 mt-1.5">Expires in 10 minutes.</p>
        </div>

        <x-primary-button class="w-full justify-center normal-case tracking-normal">
            {{ __('Continue') }}
        </x-primary-button>
    </form>

    <div class="mt-5 flex items-center justify-between text-sm">
        <form method="POST" action="{{ route('login.otp.resend') }}">
            @csrf
            <button type="submit" class="text-orange-600 hover:text-orange-700 font-medium">
                {{ __('Resend code') }}
            </button>
        </form>
        <a href="{{ route('login') }}" class="text-slate-500 hover:text-slate-700">
            {{ __('Back') }}
        </a>
    </div>
</x-guest-layout>
