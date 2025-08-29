<?php

namespace App\Console\Commands;

use App\Jobs\ExampleEventConsumer;
use App\Messaging\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PublishExampleEvent extends Command
{
    protected $signature = 'mq:publish-example {--producer=queue : queue|amqp}';
    protected $description = 'Publish an example event to RabbitMQ (queue dispatch or AMQP publish)';

    public function handle(): int
    {
        $payload = ['id' => (string) Str::uuid(), 'ts' => now()->toIso8601String()];
        $headers = [
            'Idempotency-Key' => $payload['id'],
            'X-Correlation-Id' => app()->bound('correlation_id') ? app('correlation_id') : (string) Str::uuid(),
        ];

        if ($this->option('producer') === 'amqp') {
            (new Publisher())->publish(config('queue.connections.rabbitmq.options.exchange_routing_key', 'events.example'), $payload, $headers);
            $this->info('Published via AMQP');
        } else {
            ExampleEventConsumer::dispatch($payload, $headers)->onConnection('rabbitmq');
            $this->info('Dispatched job to rabbitmq queue');
        }

        return self::SUCCESS;
    }
}

