<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApiResponse
{
    private const STATUS_CODE_MAP = [
        400 => 'bad_request',
        401 => 'unauthorized',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        422 => 'validation_error',
        429 => 'too_many_requests',
        500 => 'internal_server_error',
        501 => 'not_implemented',
        502 => 'bad_gateway',
        503 => 'service_unavailable',
        504 => 'gateway_timeout',
    ];

    public static function success(mixed $data = null, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = self::wrapSuccess($data, $message, $meta);
        return response()->json($payload, $status);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        array $details = [],
        array $context = []
    ): JsonResponse {
        $payload = self::wrapError(
            status: $status,
            code: $code,
            message: $message,
            details: $details,
            context: $context
        );

        return response()->json($payload, $status);
    }

    public static function wrapSuccess(mixed $data = null, ?string $message = null, array $meta = []): array
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return $payload;
    }

    public static function wrapError(
        int $status,
        ?string $code = null,
        ?string $message = null,
        mixed $details = null,
        array $context = []
    ): array {
        $code ??= self::canonicalErrorCode($status);
        $message ??= self::defaultMessageForStatus($status);

        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null && $details !== [] && $details !== '') {
            $error['details'] = $details;
        }

        $trace = array_replace(self::traceContext(), $context);
        if (isset($trace['traceId'])) {
            $error['traceId'] = $trace['traceId'];
        }
        if (isset($trace['correlationId'])) {
            $error['correlationId'] = $trace['correlationId'];
        }
        if (isset($trace['spanId'])) {
            $error['spanId'] = $trace['spanId'];
        }
        if (isset($trace['traceParent'])) {
            $error['traceParent'] = $trace['traceParent'];
        }
        if (isset($trace['traceState'])) {
            $error['traceState'] = $trace['traceState'];
        }
        if (isset($trace['meta']) && is_array($trace['meta']) && !empty($trace['meta'])) {
            $error['meta'] = $trace['meta'];
        }

        return [
            'success' => false,
            'error' => $error,
        ];
    }

    public static function isEnveloped(mixed $payload): bool
    {
        if (!is_array($payload) || !array_key_exists('success', $payload) || !is_bool($payload['success'])) {
            return false;
        }

        if ($payload['success'] === true) {
            return array_key_exists('data', $payload);
        }

        return array_key_exists('error', $payload);
    }

    public static function canonicalErrorCode(int $status): string
    {
        return self::STATUS_CODE_MAP[$status] ?? ($status >= 500 ? 'server_error' : 'error');
    }

    public static function defaultMessageForStatus(int $status): string
    {
        $default = SymfonyResponse::$statusTexts[$status] ?? 'Error';

        if ($status >= 500 && !config('app.debug')) {
            return 'An unexpected error occurred.';
        }

        return $default;
    }

    public static function traceContext(): array
    {
        $context = [];

        if (app()->bound('trace_id')) {
            $context['traceId'] = (string) app('trace_id');
        }
        if (app()->bound('correlation_id')) {
            $context['correlationId'] = (string) app('correlation_id');
        }
        if (app()->bound('span_id')) {
            $context['spanId'] = (string) app('span_id');
        }
        if (app()->bound('trace_id') && app()->bound('span_id')) {
            $flags = app()->bound('trace_flags') ? (string) app('trace_flags') : '01';
            $flags = substr(strtolower($flags ?: '01'), 0, 2);
            $context['traceParent'] = sprintf(
                '00-%s-%s-%s',
                (string) app('trace_id'),
                (string) app('span_id'),
                $flags
            );
        }
        if (app()->bound('tracestate')) {
            $context['traceState'] = (string) app('tracestate');
        }

        return $context;
    }
}
