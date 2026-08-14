<?php
namespace App\Helpers;

/**
 * Centralized logging system for AttendPro
 * Logs to logs/application.log with rotation support
 */
class Logger
{
    private static string $logDir = '';
    private static string $logFile = '';
    private static array $levels = [
        'DEBUG'     => 100,
        'INFO'      => 200,
        'NOTICE'    => 250,
        'WARNING'   => 300,
        'ERROR'     => 400,
        'CRITICAL'  => 500,
        'ALERT'     => 550,
        'EMERGENCY' => 600,
    ];

    public static function init(): void
    {
        self::$logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0755, true);
        }
        self::$logFile = self::$logDir . DIRECTORY_SEPARATOR . 'application.log';
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::log('NOTICE', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('CRITICAL', $message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        self::log('ALERT', $message, $context);
    }

    public static function emergency(string $message, array $context = []): void
    {
        self::log('EMERGENCY', $message, $context);
    }

    /**
     * Log an exception with full stack trace.
     */
    public static function exception(\Throwable $e, string $context = ''): void
    {
        $message = $context ?: $e->getMessage();
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
        self::error($message, $context);
    }

    /**
     * Log an SQL error with query details.
     */
    public static function sql(string $sql, array $params = [], string $error = ''): void
    {
        self::error('SQL Error', [
            'sql' => $sql,
            'params' => $params,
            'error' => $error,
        ]);
    }

    /**
     * Log an AJAX error.
     */
    public static function ajax(string $url, string $method, int $status, string $error = ''): void
    {
        self::warning('AJAX Error', [
            'url' => $url,
            'method' => $method,
            'status' => $status,
            'error' => $error,
        ]);
    }

    /**
     * Core logging method.
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if (!self::$logFile) {
            self::init();
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $levelValue = self::$levels[$levelUpper] ?? 200;

        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = "[{$timestamp}] [{$levelUpper}] {$message}{$contextStr}" . PHP_EOL;

        try {
            file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Silently fail if logging itself fails
        }
    }

    /**
     * Rotate log files when they exceed max size.
     */
    public static function rotate(): void
    {
        if (!self::$logFile) {
            self::init();
        }

        if (!file_exists(self::$logFile)) {
            return;
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        $fileSize = filesize(self::$logFile);

        if ($fileSize > $maxSize) {
            $archive = self::$logFile . '.' . date('Y-m-d-His') . '.log';
            try {
                rename(self::$logFile, $archive);
            } catch (\Throwable $e) {
                // Silently fail
            }

            // Clean up old archives (keep last 10)
            $files = glob(self::$logDir . DIRECTORY_SEPARATOR . 'application.log.*.log');
            if (count($files) > 10) {
                usort($files, function ($a, $b) {
                    return filemtime($a) - filemtime($b);
                });
                $filesToDelete = array_slice($files, 0, count($files) - 10);
                foreach ($filesToDelete as $file) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Get recent log entries for display.
     */
    public static function getRecent(int $lines = 100): array
    {
        if (!self::$logFile) {
            self::init();
        }

        if (!file_exists(self::$logFile)) {
            return [];
        }

        $content = file_get_contents(self::$logFile);
        $allLines = explode(PHP_EOL, trim($content));
        return array_slice($allLines, -$lines);
    }
}
