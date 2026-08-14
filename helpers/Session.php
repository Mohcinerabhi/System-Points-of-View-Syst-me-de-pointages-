<?php
namespace App\Helpers;

/**
 * Gestion des sessions et des messages flash.
 */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (session_id()) {
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function flash(string $key, $message = null)
    {
        self::start();
        $flashKey = 'flash_' . $key;
        if ($message === null) {
            $value = self::get($flashKey);
            self::remove($flashKey);
            return $value;
        }
        self::set($flashKey, $message);
    }
}
