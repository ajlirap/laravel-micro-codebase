<?php

return [
    'enabled' => (bool) env('OTEL_ENABLED', true),
    'service_name' => env('OTEL_SERVICE_NAME', env('APP_NAME', 'laravel-micro')),
    'instrumentation_name' => env('OTEL_INSTRUMENTATION_NAME', 'laravel-micro-codebase'),
    'environment' => env('OTEL_RESOURCE_ATTRIBUTES_ENVIRONMENT', env('APP_ENV', 'local')),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://otel-collector:4318'),
    'protocol' => env('OTEL_EXPORTER_OTLP_PROTOCOL', 'http/protobuf'),
    'traces_exporter' => env('OTEL_TRACES_EXPORTER', 'otlp'),
    'processor' => env('OTEL_PHP_TRACES_PROCESSOR', 'batch'),
    'propagators' => env('OTEL_PROPAGATORS', 'tracecontext,baggage'),
    'database_spans' => (bool) env('OTEL_DATABASE_SPANS', true),
    'queue_spans' => (bool) env('OTEL_QUEUE_SPANS', true),
];
