<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiQuotaTracker
{
    private const WARNING_THRESHOLD_RATIO = 0.8;

    public function recordRequest(string $service, int $count = 1): void
    {
        $used = $this->incrementAtomically($this->cacheKey($service), $count);
        $limit = $this->dailyLimit($service);

        if ($limit !== null && $used >= (int) ($limit * self::WARNING_THRESHOLD_RATIO)) {
            Log::warning("API quota {$used}/{$limit} used for {$service}");
        }
    }

    public function hasQuota(string $service): bool
    {
        $limit = $this->dailyLimit($service);

        if ($limit === null) {
            return true;
        }

        $used = (int) Cache::get($this->cacheKey($service), 0);

        return $used < $limit;
    }

    /**
     * Atomically increments the daily counter. Seeds the key with an
     * end-of-day expiry on first touch (via Cache::add, a no-op if the key
     * already exists) so stores that support Cache::increment don't create
     * a key that never expires. Falls back to a get-then-put pattern if the
     * active cache store doesn't support atomic increments.
     */
    private function incrementAtomically(string $key, int $count): int
    {
        Cache::add($key, 0, now()->endOfDay());

        try {
            $value = Cache::increment($key, $count);

            if ($value !== false) {
                return (int) $value;
            }
        } catch (\Throwable $e) {
            // Store doesn't support atomic increments (e.g. some custom
            // drivers); fall back below.
        }

        $current = (int) Cache::get($key, 0);
        $updated = $current + $count;
        Cache::put($key, $updated, now()->endOfDay());

        return $updated;
    }

    private function dailyLimit(string $service): ?int
    {
        $limit = config("services.{$service}.daily_limit");

        return $limit !== null ? (int) $limit : null;
    }

    private function cacheKey(string $service): string
    {
        return "api_quota_{$service}_".date('Y-m-d');
    }
}
