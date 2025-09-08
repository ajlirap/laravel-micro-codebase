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

            $format = env('LOG_FORMAT', 'json'); // 'json' | 'line'

            // Apply formatter per handler; also push processor at handler level when supported
            foreach ($monolog->getHandlers() as $handler) {
                if ($handler instanceof FormattableHandlerInterface) {
                    if ($format === 'line') {
                        $handler->setFormatter(new SerilogStyleFormatter());
                    } else {
                        $handler->setFormatter(new JsonFormatter());
                    }
                }
                if ($handler instanceof ProcessableHandlerInterface) {
                    $handler->pushProcessor(new CorrelationProcessor());
                }
            }
        }
    }
}
