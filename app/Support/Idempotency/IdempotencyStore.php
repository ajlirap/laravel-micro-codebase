<?php

namespace App\Support\Idempotency;

use Illuminate\Support\Facades\Cache;

class IdempotencyStore
{
    public function has(string $key): bool
    {
        return Cache::has($this->k($key));
    }

    public function remember(string $key, int $ttlSeconds = 86400): void
    {
        Cache::put($this->k($key), 1, $ttlSeconds);
    }

    private function k(string $key): string
    {
        return 'idem:'.$key;
    }
}

