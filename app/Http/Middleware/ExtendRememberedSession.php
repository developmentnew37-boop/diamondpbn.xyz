<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ExtendRememberedSession
{
    public const LIFETIME_MINUTES = 60 * 24 * 7;

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ($request->session()->get('auth.remember_week') || Auth::viaRemember())) {
            if (Auth::viaRemember()) {
                $request->session()->put('auth.remember_week', true);
            }

            config(['session.lifetime' => self::LIFETIME_MINUTES]);
        }

        return $next($request);
    }
}
