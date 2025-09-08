<?php

namespace App\Logging;

use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Handler\ProcessableHandlerInterface;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger as MonologLogger;

class TapJsonFormatter
{
    // In Laravel, tap receives Illuminate\Log\Logger (a wrapper). Use getLogger() to access Monolog.
    public function __invoke(IlluminateLogger $logger): void
    {
        $monolog = $logger->getLogger();
        if ($monolog instanceof MonologLogger) {
            // Attach a processor at the logger level (works regardless of handler type)
            $monolog->pushProcessor(new CorrelationProcessor());

            // Apply JSON formatter only to handlers that support it; add processor per handler when supported
            foreach ($monolog->getHandlers() as $handler) {
                if ($handler instanceof FormattableHandlerInterface) {
                    $handler->setFormatter(new JsonFormatter());
                }
                if ($handler instanceof ProcessableHandlerInterface) {
                    $handler->pushProcessor(new CorrelationProcessor());
                }
            }
        }
    }
}
