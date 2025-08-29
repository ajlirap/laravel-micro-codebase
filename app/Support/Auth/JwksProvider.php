<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class JwksProvider
{
    public function getKeys(): array
    {
        $url = config('micro.security.jwt.jwks_url');
        if (!$url) return [];
        return Cache::remember('jwks:'.md5($url), (int) config('micro.security.jwt.cache_ttl_seconds', 3600), function () use ($url) {
            $resp = Http::get($url);
            $resp->throw();
            $data = $resp->json();
            return $data['keys'] ?? [];
        });
    }

    public function findKey(string $kid): ?array
    {
        foreach ($this->getKeys() as $key) {
            if (($key['kid'] ?? null) === $kid) return $key;
        }
        return null;
    }
}

