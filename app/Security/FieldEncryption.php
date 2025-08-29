<?php

namespace App\Security;

use Illuminate\Support\Str;

class FieldEncryption
{
    // Uses sodium/openssl via Laravel's encrypter is an option, but here support versioned keys for rotation
    public static function encrypt(string $plaintext, ?string $version = null): string
    {
        $version = $version ?: config('micro.security.field_encryption.active_version', 'v1');
        $key = static::keyFor($version);
        if (!$key) {
            throw new \RuntimeException("Encryption key for {$version} not configured");
        }
        $iv = random_bytes(16);
        $cipher = 'aes-256-gcm';
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, $cipher, static::binKey($key), OPENSSL_RAW_DATA, $iv, $tag);
        return implode(':', [$version, base64_encode($iv), base64_encode($tag), base64_encode($ciphertext)]);
    }

    public static function decrypt(string $value): string
    {
        [$version, $ivB64, $tagB64, $ctB64] = explode(':', $value, 4);
        $key = static::keyFor($version);
        if (!$key) {
            // Try all keys to support rotation
            foreach (config('micro.security.field_encryption.keys', []) as $v => $k) {
                $key = $k;
                $plaintext = static::tryDecrypt($key, $ivB64, $tagB64, $ctB64);
                if (!is_null($plaintext)) return $plaintext;
            }
            throw new \RuntimeException('No valid key for decryption');
        }
        $plaintext = static::tryDecrypt($key, $ivB64, $tagB64, $ctB64);
        if (is_null($plaintext)) throw new \RuntimeException('Decryption failed');
        return $plaintext;
    }

    private static function tryDecrypt(string $key, string $ivB64, string $tagB64, string $ctB64): ?string
    {
        $cipher = 'aes-256-gcm';
        $iv = base64_decode($ivB64);
        $tag = base64_decode($tagB64);
        $ct = base64_decode($ctB64);
        $pt = openssl_decrypt($ct, $cipher, static::binKey($key), OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? null : $pt;
    }

    private static function keyFor(string $version): ?string
    {
        $keys = config('micro.security.field_encryption.keys', []);
        return $keys[$version] ?? null;
    }

    private static function binKey(string $base64): string
    {
        $raw = base64_decode($base64, true);
        if ($raw === false) {
            throw new \RuntimeException('Field encryption key must be base64 encoded');
        }
        return $raw;
    }
}

