<?php
/**
 * Autoloader PSR-4 pour les espaces de noms "App\" et "Attendance\"
 * Mappe App\Controllers -> controllers/
 *        App\Models    -> models/
 *        App\Helpers   -> helpers/
 *        App\Core      -> core/
 *        Attendance\Services -> services/
 */

spl_autoload_register(function ($class) {
    $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR;

    $map = [
        'App\\' => $baseDir,
        'Attendance\\' => $baseDir,
    ];

    foreach ($map as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (is_readable($path)) {
            require $path;
            return;
        }
    }
});
