<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginOtpController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.login-otp', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request, LoginOtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('info', 'Your session expired. Please log in again.');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $this->ensureVerifyNotRateLimited($request, $user);

        if (! $otp->verify($user, $request->input('code'))) {
            RateLimiter::hit($this->verifyThrottleKey($request, $user));

            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        RateLimiter::clear($this->verifyThrottleKey($request, $user));

        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request, LoginOtpService $otp): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->with('info', 'Your session expired. Please log in again.');
        }

        if (! $otp->canResend($user->id)) {
            return back()->with('info', 'Please wait a minute before requesting a new code.');
        }

        try {
            $otp->send($user);
            $otp->markResent($user->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send verification email. Check mail settings or try again later.');
        }

        return back()->with('status', 'A new verification code was sent to your email.');
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('login.id');
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    private function verifyThrottleKey(Request $request, User $user): string
    {
        return 'login-otp-verify:'.$user->id.'|'.$request->ip();
    }

    private function ensureVerifyNotRateLimited(Request $request, User $user): void
    {
        $key = $this->verifyThrottleKey($request, $user);

        if (! RateLimiter::tooManyAttempts($key, 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'code' => 'Too many attempts. Try again in '.$seconds.' seconds.',
        ]);
    }
}
