<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\LoginOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, LoginOtpService $otp): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();
        Auth::logout();

        $request->session()->put([
            'login.id' => $user->id,
            'login.remember' => $request->boolean('remember'),
        ]);

        try {
            $otp->send($user);
        } catch (\Throwable $e) {
            report($e);
            $request->session()->forget(['login.id', 'login.remember']);

            return back()->withInput($request->only('email', 'remember'))
                ->with('error', 'Could not send verification code to your email. Please check mail configuration or try again.');
        }

        return redirect()->route('login.otp');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
