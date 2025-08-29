<?php

namespace App\Logging;

use Monolog\LogRecord;

class CorrelationProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $record->extra['correlation_id'] = app()->bound('correlation_id') ? app('correlation_id') : null;
            $record->extra['request_id'] = app()->bound('request_id') ? app('request_id') : null;
            if (auth()->check()) {
                $user = auth()->user();
                $record->extra['auth'] = [
                    'sub' => method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null,
                ];
            }
        } catch (\Throwable $e) {
            // no-op
        }
        return $record;
    }
}

