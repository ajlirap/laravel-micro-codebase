<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderNotConfiguredException extends RuntimeException
{
    public static function forProvider(string $provider): self
    {
        return new self("Provider '{$provider}' is not configured.");
    }
}
