<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class LoginOtpService
{
    public const TTL_MINUTES = 10;

    public function cacheKey(int $userId): string
    {
        return 'login_otp:user:'.$userId;
    }

    public function resendThrottleKey(int $userId): string
    {
        return 'login_otp_resend:user:'.$userId;
    }

    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        Cache::put(
            $this->cacheKey($user->id),
            Hash::make($code),
            now()->addMinutes(self::TTL_MINUTES)
        );

        Mail::to($user->email)->send(new LoginOtpMail($code, $user));
    }

    public function verify(User $user, string $code): bool
    {
        $code = trim($code);
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $hash = Cache::get($this->cacheKey($user->id));
        if (! is_string($hash) || $hash === '') {
            return false;
        }

        if (! Hash::check($code, $hash)) {
            return false;
        }

        Cache::forget($this->cacheKey($user->id));

        return true;
    }

    public function canResend(int $userId): bool
    {
        return ! Cache::has($this->resendThrottleKey($userId));
    }

    public function markResent(int $userId): void
    {
        Cache::put($this->resendThrottleKey($userId), true, now()->addMinute());
    }
}
