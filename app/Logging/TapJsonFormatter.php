<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Formatter\JsonFormatter;

class TapJsonFormatter
{
    // In Laravel, tap receives Illuminate\Log\Logger (a wrapper). Use getLogger() to access Monolog.
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        foreach ($monolog->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter());
            $handler->pushProcessor(new CorrelationProcessor());
        }
    }
}
