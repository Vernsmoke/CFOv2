<?php

namespace App\Services;

use Illuminate\Support\Facades\{Cache, RateLimiter};

class RecoveryRequestLimiter
{
    private const MAX_REQUESTS = 5;
    private const WINDOW_SECONDS = 3600;

    public static function status(string $email, bool $consume = false): array
    {
        $key = 'recovery:'.hash('sha256', strtolower(trim($email)));

        // Serialize the check and increment so simultaneous submissions cannot
        // exceed the limit. The cooldown starts with the fifth accepted request.
        return Cache::lock($key.':lock', 5)->block(3, function () use ($key, $consume) {
            $cooldownKey = $key.':cooldown';
            $retryAfter = RateLimiter::availableIn($cooldownKey);
            $allowed = $retryAfter === 0;

            if ($allowed && $consume) {
                if (RateLimiter::hit($key, self::WINDOW_SECONDS) >= self::MAX_REQUESTS) {
                    RateLimiter::hit($cooldownKey, self::WINDOW_SECONDS);
                    $retryAfter = self::WINDOW_SECONDS;
                }
            }

            return [
                'allowed' => $allowed,
                'max_requests' => self::MAX_REQUESTS,
                'remaining_requests' => $retryAfter > 0 ? 0 : RateLimiter::remaining($key, self::MAX_REQUESTS),
                'retry_after' => $retryAfter,
                'reset_after' => $retryAfter > 0 ? $retryAfter : RateLimiter::availableIn($key),
            ];
        });
    }
}
