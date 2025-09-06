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
        $ttl = (int) config('micro.security.jwt.cache_ttl_seconds', 3600);
        return Cache::remember('jwks:'.md5($url), $ttl, function () use ($url) {
            try { logger()->debug('Fetching JWKS from remote', [ 'url' => $url ]); } catch (\Throwable) {}
            $resp = Http::get($url);
            $resp->throw();
            $data = $resp->json();
            $keys = $data['keys'] ?? [];
            try { logger()->debug('Fetched JWKS', [ 'keys_count' => is_array($keys) ? count($keys) : 0 ]); } catch (\Throwable) {}
            return $keys;
        });
    }

    public function findKey(string $kid): ?array
    {
        $keys = $this->getKeys();
        try { logger()->debug('Searching JWKS for kid', [ 'kid' => $kid, 'available_kids' => array_values(array_filter(array_map(static fn($k) => $k['kid'] ?? null, $keys))) ]); } catch (\Throwable) {}
        foreach ($keys as $key) {
            if (($key['kid'] ?? null) === $kid) return $key;
        }
        return null;
    }
}
