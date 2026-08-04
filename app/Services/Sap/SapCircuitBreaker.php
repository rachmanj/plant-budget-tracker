<?php

namespace App\Services\Sap;

use Illuminate\Support\Facades\Cache;

class SapCircuitBreaker
{
    private const FAILURE_THRESHOLD = 5;

    private const WINDOW_SECONDS = 120;

    private const COOLDOWN_SECONDS = 60;

    public function isOpen(string $channel): bool
    {
        return Cache::has("sap_breaker_open:{$channel}");
    }

    public function recordFailure(string $channel): void
    {
        $key = "sap_breaker_failures:{$channel}";
        $failures = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $failures, self::WINDOW_SECONDS);

        if ($failures >= self::FAILURE_THRESHOLD) {
            Cache::put("sap_breaker_open:{$channel}", true, self::COOLDOWN_SECONDS);
            Cache::forget($key);
        }
    }

    public function recordSuccess(string $channel): void
    {
        Cache::forget("sap_breaker_failures:{$channel}");
        Cache::forget("sap_breaker_open:{$channel}");
    }

    public function reset(string $channel): void
    {
        $this->recordSuccess($channel);
    }
}
