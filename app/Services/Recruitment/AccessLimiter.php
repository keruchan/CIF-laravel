<?php

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\RateLimiter;

final class AccessLimiter
{
    public static function check(string $kind, int $maximum = 20): void
    {
        $key = 'recruitment:'.hash('sha256', $kind.':'.request()->ip());
        RateLimiter::hit($key, 900);
        if (RateLimiter::tooManyAttempts($key, $maximum + 1)) {
            throw new HttpResponseException(response()->json([
                'error' => 'Too many attempts. Please try again in 15 minutes.',
            ], 429, ['Retry-After' => '900']));
        }
    }
}
