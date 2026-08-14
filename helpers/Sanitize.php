<?php
namespace App\Helpers;

/**
 * Fonctions d'assainissement (sanitization) des entrées utilisateur.
 */
class Sanitize
{
    public static function string($value, int $maxLength = 0): string
    {
        if (is_array($value)) {
            return '';
        }
        $value = trim((string) $value);
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            $value = mb_substr($value, 0, $maxLength);
        }
        return $value;
    }

    public static function int($value, ?int $default = null): ?int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function float($value, ?float $default = null): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function email($value): string
    {
        return filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL);
    }

    public static function bool($value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'y'], true);
    }

    public static function date($value, ?string $default = null): ?string
    {
        if (empty($value)) {
            return $default;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        return $dt ? $dt->format('Y-m-d') : $default;
    }

    public static function time($value, ?string $default = null): ?string
    {
        if (empty($value)) {
            return $default;
        }
        $dt = \DateTime::createFromFormat('H:i:s', $value) ?: \DateTime::createFromFormat('H:i', $value);
        return $dt ? $dt->format('H:i:s') : $default;
    }

    public static function array($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_map(function ($v) {
            return is_array($v) ? self::array($v) : self::string($v);
        }, $value);
    }

    public static function filename($value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', (string) $value);
        return trim($value, '_');
    }
}
