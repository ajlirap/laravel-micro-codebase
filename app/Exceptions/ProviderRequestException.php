<?php

namespace App\Exceptions;

use RuntimeException;

class ProviderRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $statusCode = null, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
