<?php

namespace App\Queue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;

class AzureServiceBusJob extends Job implements JobContract
{
    public function __construct(
        Container $container,
        private readonly AzureServiceBusQueue $queue,
        private readonly string $payload,
        string $connectionName,
        private readonly string $queueName,
        private readonly array $headers = []
    ) {
        $this->container = $container;
        $this->connectionName = $connectionName;
    }

    public function getJobId(): string
    {
        return (string) (json_decode($this->payload, true)['uuid'] ?? md5($this->payload));
    }

    public function getRawBody(): string
    {
        return $this->payload;
    }

    public function delete(): void
    {
        parent::delete();
    }

    public function release($delay = 0): void
    {
        parent::release($delay);
        $this->queue->pushRaw($this->payload, $this->queueName, ['headers' => $this->headers]);
    }
}
