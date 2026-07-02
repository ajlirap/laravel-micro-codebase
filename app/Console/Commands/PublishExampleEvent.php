<?php

namespace App\Console\Commands;

use App\Jobs\ExampleEventConsumer;
use App\Messaging\Publisher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PublishExampleEvent extends Command
{
    protected $signature = 'mq:publish-example {--producer=queue : queue|amqp}';
    protected $description = 'Publish an example event to the configured queue backend';

    public function handle(): int
    {
        $payload = ['id' => (string) Str::uuid(), 'ts' => now()->toIso8601String()];
        $headers = [
            'Idempotency-Key' => $payload['id'],
            'X-Correlation-Id' => app()->bound('correlation_id') ? app('correlation_id') : (string) Str::uuid(),
        ];

        if ($this->option('producer') === 'amqp') {
            if (config('queue.default') !== 'azure-servicebus') {
                $this->error('Direct publish is implemented for QUEUE_CONNECTION=azure-servicebus.');

                return self::FAILURE;
            }
            (new Publisher())->publish('events.example', $payload, $headers);
            $this->info('Published directly via azure-servicebus');
        } else {
            ExampleEventConsumer::dispatch($payload, $headers);
            $this->info('Dispatched job to '.config('queue.default').' queue');
        }

        return self::SUCCESS;
    }
}
