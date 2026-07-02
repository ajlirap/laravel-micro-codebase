<?php

namespace App\Messaging;

use Illuminate\Support\Facades\Queue;
use RuntimeException;

class Publisher
{
    public function publish(string $routingKey, array $message, array $headers = []): void
    {
        $connection = config('queue.default');

        if ($connection !== 'azure-servicebus') {
            throw new RuntimeException("Publishing is implemented for azure-servicebus; current queue connection is [$connection].");
        }

        Queue::connection($connection)->pushRaw(json_encode([
            'uuid' => $message['id'] ?? (string) \Illuminate\Support\Str::uuid(),
            'displayName' => $routingKey,
            'job' => 'App\\Jobs\\ExampleEventConsumer@handle',
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'retryUntil' => null,
            'data' => [
                'commandName' => \App\Jobs\ExampleEventConsumer::class,
                'command' => serialize(new \App\Jobs\ExampleEventConsumer($message, $headers)),
            ],
        ]), null, ['headers' => array_merge($headers, ['Routing-Key' => $routingKey])]);
    }
}
