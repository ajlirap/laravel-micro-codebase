<?php

namespace App\Logging;

use Monolog\LogRecord;
use Illuminate\Support\Facades\Auth;

class CorrelationProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $record->extra['correlation_id'] = app()->bound('correlation_id') ? app('correlation_id') : null;
            $record->extra['request_id'] = app()->bound('request_id') ? app('request_id') : null;
            // Use Auth facade and ensure the auth manager is bound before querying
            if (app()->bound('auth') && Auth::check()) {
                $user = Auth::user();
                $sub = null;
                if (is_object($user)) {
                    if (method_exists($user, 'getAuthIdentifier')) {
                        $sub = $user->getAuthIdentifier();
                    } elseif (property_exists($user, 'id')) {
                        $sub = $user->id;
                    }
                }
                $record->extra['auth'] = ['sub' => $sub];
            }
        } catch (\Throwable $e) {
            // no-op
        }
        return $record;
    }
}
