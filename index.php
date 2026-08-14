<?php
/**
 * AttendPro - Front Controller
 * Development error reporting enabled
 */

// Enable error reporting in development
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'php_errors.log');

// Global error handler
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    \App\Helpers\Logger::error("PHP Error: {$message}", [
        'severity' => $severity,
        'file' => $file,
        'line' => $line,
    ]);
    if (headers_sent()) {
        echo "<div style='color:red;padding:10px;border:1px solid red;margin:10px;'>Error: {$message} in {$file}:{$line}</div>";
    }
});

// Global exception handler
set_exception_handler(function ($e) {
    \App\Helpers\Logger::exception($e, 'Uncaught exception');
    http_response_code(500);
    echo '<h1>500 - Erreur serveur</h1>';
    exit;
});

if (!function_exists('__')) {
    function __(string $key, string $default = ''): string
    {
        return \App\Helpers\Language::get($key, $default);
    }
}

// Initialize logging
require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'autoload.php';
\App\Helpers\Logger::init();
\App\Helpers\Logger::rotate();

use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Logger;
use App\Helpers\Language;

Session::start();
Language::init();

$controllerName = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['controller'] ?? $_REQUEST['module'] ?? '');
$action = preg_replace('/[^a-zA-Z0-9_]/', '', $_REQUEST['action'] ?? '');

if ($controllerName === '' && $action === '') {
    header('Location: login.php');
    exit;
}

if ($controllerName === '') {
    $controllerName = 'auth';
}
if ($action === '') {
    $action = 'index';
}

$controllerClass = 'App\\Controllers\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $controllerName))) . 'Controller';

if (!class_exists($controllerClass)) {
    Logger::error("Controller not found: {$controllerClass}");
    http_response_code(404);
    echo '<h1>404 - Contrôleur introuvable</h1>';
    exit;
}

try {
    $controller = new $controllerClass();
} catch (\Throwable $e) {
    Logger::exception($e, "Failed to instantiate controller: {$controllerClass}");
    http_response_code(500);
    echo '<h1>500 - Erreur serveur</h1>';
    exit;
}

if (!method_exists($controller, $action)) {
    Logger::error("Action not found: {$controllerClass}::{$action}");
    http_response_code(404);
    echo '<h1>404 - Action introuvable</h1>';
    exit;
}

try {
    $controller->$action();
} catch (\Throwable $e) {
    Logger::exception($e, "Unhandled exception in {$controllerClass}::{$action}");
    http_response_code(500);
    echo '<h1>500 - Erreur serveur</h1>';
    exit;
}
