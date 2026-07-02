<?php

namespace App\Queue;

use App\Azure\ServiceBus\ServiceBusClient;
use App\Telemetry\Telemetry;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use OpenTelemetry\API\Trace\SpanKind;

class AzureServiceBusQueue extends Queue implements QueueContract
{
    private ServiceBusClient $client;
    private string $defaultQueue;
    private int $receiveTimeout;

    public function __construct(
        ?ServiceBusClient $client = null,
        ?string $defaultQueue = null,
        ?int $receiveTimeout = null,
    )
    {
        $this->client = $client ?? ServiceBusClient::fromLaravelConfig();
        $this->defaultQueue = $defaultQueue ?? (string) config('queue.connections.azure-servicebus.queue', 'default');
        $this->receiveTimeout = $receiveTimeout ?? (int) config('queue.connections.azure-servicebus.receive_timeout', 30);
    }

    public function size($queue = null): int
    {
        return 0;
    }

    public function pendingSize($queue = null): int
    {
        return 0;
    }

    public function delayedSize($queue = null): int
    {
        return 0;
    }

    public function reservedSize($queue = null): int
    {
        return 0;
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        return null;
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->pushRaw($this->createPayload($job, $this->getQueue($queue), $data), $queue);
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $queueName = $this->getQueue($queue);

        $this->traceQueue('messaging.publish', SpanKind::KIND_PRODUCER, $queueName, function () use ($queueName, $payload, $options) {
            $this->client->send($queueName, $payload, $options['headers'] ?? []);
        });

        return json_decode($payload, true)['uuid'] ?? null;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        return $this->push($job, $data, $queue);
    }

    public function pop($queue = null): ?AzureServiceBusJob
    {
        $queueName = $this->getQueue($queue);
        $message = $this->traceQueue('messaging.receive', SpanKind::KIND_CONSUMER, $queueName, function () use ($queueName) {
            return $this->client->receiveAndDelete($queueName, $this->receiveTimeout);
        });

        if ($message === null) {
            return null;
        }

        return new AzureServiceBusJob(
            $this->container,
            $this,
            $message['body'],
            $this->connectionName,
            $queueName,
            $message['headers']
        );
    }

    public function getQueue($queue): string
    {
        return $queue ?: $this->defaultQueue;
    }

    private function traceQueue(string $name, int $kind, string $queueName, callable $callback): mixed
    {
        if (!config('otel.enabled') || !config('otel.queue_spans')) {
            return $callback();
        }

        return app(Telemetry::class)->trace($name, $kind, [
            'messaging.system' => 'azure_service_bus',
            'messaging.destination.name' => $queueName,
            'messaging.operation.name' => $name,
        ], $callback);
    }
}
