<?php

declare(strict_types=1);

namespace Attendance\Services;

use DateTime;

/**
 * Writes detailed synchronization debug information to storage/logs/sync_debug.log.
 *
 * Every step of the sync process — HTTP requests, parser results, DB operations,
 * and exceptions — is logged here with full context so errors are never hidden.
 */
class SyncDebugLogger
{
    private static string $logFile = '';

    public static function init(): void
    {
        self::$logFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'sync_debug.log';
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public static function log(string $step, string $method, array $context = []): void
    {
        if (!self::$logFile) {
            self::init();
        }

        $timestamp = (new DateTime())->format('Y-m-d H:i:s.u');
        $lines = [];
        $lines[] = "=== [{$timestamp}] Step: {$step} | Method: {$method} ===";

        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $lines[] = "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            } elseif (is_string($value) && strlen($value) > 2000) {
                $lines[] = "  {$key}: " . substr($value, 0, 2000) . "... [truncated]";
            } else {
                $lines[] = "  {$key}: " . var_export($value, true);
            }
        }

        $lines[] = "";

        @file_put_contents(self::$logFile, implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
    }

    public static function logException(string $step, string $method, \Throwable $e, array $context = []): void
    {
        $exceptionContext = array_merge($context, [
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'exception_trace' => $e->getTraceAsString(),
        ]);

        self::log($step, $method, $exceptionContext);
    }
}