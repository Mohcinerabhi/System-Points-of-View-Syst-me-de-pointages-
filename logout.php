<?php
/**
 * Déconnexion - Attendance Pro
 * Fichier: logout.php
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'autoload.php';

use App\Helpers\Auth;
use App\Helpers\Session;

if (Auth::check()) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'AuthController.php';
    if (class_exists('App\Controllers\AuthController')) {
        $c = new \App\Controllers\AuthController();
        $c->logout();
    }
}

Session::destroy();
header('Location: index.php?controller=auth&action=login');
exit;
