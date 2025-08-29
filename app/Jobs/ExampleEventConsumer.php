<?php

namespace App\Jobs;

use App\Support\Idempotency\IdempotencyStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ExampleEventConsumer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public function backoff(): array
    {
        return [1, 5, 15, 30, 60];
    }

    public function middleware(): array
    {
        return [
            new WithoutOverlapping('example-event-consumer'),
        ];
    }

    public function __construct(public array $payload, public array $headers = [])
    {
        $this->onConnection('rabbitmq');
        $this->onQueue(config('queue.connections.rabbitmq.queue'));
    }

    public function handle(IdempotencyStore $store): void
    {
        $idempotencyKey = $this->headers['Idempotency-Key'] ?? $this->payload['id'] ?? null;
        if ($idempotencyKey && $store->has($idempotencyKey)) {
            // already processed
            return;
        }

        // Process the message (business logic placeholder)
        // ...

        if ($idempotencyKey) {
            $store->remember($idempotencyKey);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Parking lot: could publish to a special queue / notify ops
        logger()->error('ExampleEventConsumer failed', [
            'error' => $e->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}

