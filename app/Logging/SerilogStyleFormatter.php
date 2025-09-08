<?php

namespace App\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\LogRecord;

class SerilogStyleFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $dt = $record->datetime->format('Y-m-d H:i:s.v P');
        $levelShort = $this->shortLevel($record->level);
        $channel = $record->channel;

        $extra = $record->extra ?? [];
        $corr = $extra['correlation_id'] ?? '_';
        $req = $extra['request_id'] ?? '';
        $corrBlock = sprintf('%s (%s)', (string) $corr, (string) $req);

        $message = (string) $record->message;

        // Render context/extra payloads compactly if present
        $contextOut = '';
        if (!empty($record->context)) {
            $contextOut = ' ' . $this->toInlineJson($record->context);
        }

        return sprintf('[%s|%s|%s|%s] %s%s%s',
            $dt,
            $levelShort,
            $corrBlock,
            $channel,
            $message,
            $contextOut,
            PHP_EOL
        );
    }

    public function formatBatch(array $records): string
    {
        $out = '';
        foreach ($records as $r) {
            if ($r instanceof LogRecord) {
                $out .= $this->format($r);
            }
        }
        return $out;
    }

    private function shortLevel(Level $level): string
    {
        return match ($level->getName()) {
            'DEBUG' => 'DBG',
            'INFO' => 'INF',
            'NOTICE' => 'NOT',
            'WARNING' => 'WRN',
            'ERROR' => 'ERR',
            'CRITICAL' => 'CRT',
            'ALERT' => 'ALR',
            'EMERGENCY' => 'EMG',
            default => $level->getName(),
        };
    }

    private function toInlineJson(array $data): string
    {
        // Prevent logging extremely large payloads
        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) { return ''; }
            // Clamp size to ~4KB for safety
            if (strlen($json) > 4096) {
                $json = substr($json, 0, 4093) . '...';
            }
            return $json;
        } catch (\Throwable) {
            return '';
        }
    }
}

