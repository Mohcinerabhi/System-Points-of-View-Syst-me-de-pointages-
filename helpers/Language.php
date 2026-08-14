<?php
/**
 * Gestion des langues / translations
 */
namespace App\Helpers;

class Language
{
    private static ?array $translations = null;
    private static string $current = 'fr';

    public static function init(): void
    {
        $lang = Session::get('app_language');

        if (!$lang) {
            $setting = (new \App\Models\Setting())->findBy('key', 'language');
            $lang = $setting ? ($setting['value'] ?? 'fr') : 'fr';
        }

        $lang = strtolower((string) $lang);
        if (!in_array($lang, ['fr', 'en', 'ar'], true)) {
            $lang = 'fr';
        }

        self::$current = $lang;
        self::load($lang);
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function load(string $lang): void
    {
        $file = __DIR__ . '/../lang/' . $lang . '.php';
        if (is_readable($file)) {
            self::$translations = require $file;
        } else {
            self::$translations = [];
        }
    }

    public static function set(string $lang): void
    {
        $lang = strtolower((string) $lang);
        if (!in_array($lang, ['fr', 'en', 'ar'], true)) {
            $lang = 'fr';
        }
        Session::set('app_language', $lang);
        self::$current = $lang;
        self::load($lang);
    }

    public static function get(string $key, string $default = ''): string
    {
        if (self::$translations === null) {
            self::init();
        }

        return self::$translations[$key] ?? ($default !== '' ? $default : $key);
    }
}
