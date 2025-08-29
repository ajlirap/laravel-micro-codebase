<?php

namespace App\Support\Http;

use Closure;
use Illuminate\Support\Carbon;

class CircuitBreaker
{
    private string $key;
    private int $failureThreshold;
    private int $resetMs;

    public function __construct(string $key, int $failureThreshold = 5, int $resetMs = 30000)
    {
        $this->key = 'cb:'.$key;
        $this->failureThreshold = $failureThreshold;
        $this->resetMs = $resetMs;
    }

    public function call(Closure $callback, Closure $onOpen = null)
    {
        $state = cache()->get($this->key.':state', 'closed');
        $failures = (int) cache()->get($this->key.':failures', 0);
        $openedAt = (int) cache()->get($this->key.':opened_at', 0);

        if ($state === 'open') {
            if (Carbon::now()->getTimestampMs() - $openedAt >= $this->resetMs) {
                $state = 'half-open';
                cache()->put($this->key.':state', $state, $this->resetMs/1000 + 5);
            } else {
                if ($onOpen) return $onOpen();
                throw new \RuntimeException('Circuit open for '.$this->key);
            }
        }

        try {
            $result = $callback();
            // Success: reset
            cache()->put($this->key.':state', 'closed', $this->resetMs/1000 + 5);
            cache()->put($this->key.':failures', 0, $this->resetMs/1000 + 5);
            cache()->forget($this->key.':opened_at');
            return $result;
        } catch (\Throwable $e) {
            $failures++;
            cache()->put($this->key.':failures', $failures, $this->resetMs/1000 + 5);
            if ($failures >= $this->failureThreshold) {
                cache()->put($this->key.':state', 'open', $this->resetMs/1000 + 5);
                cache()->put($this->key.':opened_at', Carbon::now()->getTimestampMs(), $this->resetMs/1000 + 5);
            }
            throw $e;
        }
    }
}

