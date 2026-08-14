<?php
/**
 * Constantes de l'application
 * Fichier: config/constants.php
 */

declare(strict_types=1);

namespace App\Config;

// Informations de l'application
define('APP_NAME', 'Attendance Pro');
define('APP_VERSION', '1.0.0');

// URL de base (auto-détection)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = rtrim(str_replace('\\', '/', $script), '/');

define('BASE_URL', $protocol . $host . $basePath);

// Chemins de fichiers
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Limites de fichiers
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 Mo en octets

// Pagination
define('ITEMS_PER_PAGE', 20);

// Session
define('SESSION_TIMEOUT', 3600); // 1 heure en secondes

// Tokens CSRF
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LENGTH', 32);

// Fuseau horaire
define('TIMEZONE', 'Africa/Casablanca');

// Définir le fuseau horaire par défaut
date_default_timezone_set(TIMEZONE);

// Types de fichiers autorisés pour l'upload
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt']);
define('ALLOWED_FILE_TYPES', array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_DOCUMENT_TYPES));

// Rôles utilisateur
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_EMPLOYEE', 'employee');

// Permissions par module et action
define('PERM_DASHBOARD_VIEW', 'dashboard.view');
define('PERM_EMPLOYEES_VIEW', 'employees.view');
define('PERM_EMPLOYEES_CREATE', 'employees.create');
define('PERM_EMPLOYEES_EDIT', 'employees.edit');
define('PERM_EMPLOYEES_DELETE', 'employees.delete');
define('PERM_ATTENDANCE_VIEW', 'attendance.view');
define('PERM_ATTENDANCE_MANAGE', 'attendance.manage');
define('PERM_REPORTS_VIEW', 'reports.view');
define('PERM_REPORTS_EXPORT', 'reports.export');
define('PERM_SETTINGS_VIEW', 'settings.view');
define('PERM_SETTINGS_EDIT', 'settings.edit');

// Statuts de pointage
define('STATUS_PRESENT', 'present');
define('STATUS_ABSENT', 'absent');
define('STATUS_LATE', 'late');
define('STATUS_EARLY_DEPARTURE', 'early_departure');
define('STATUS_HALF_DAY', 'half_day');
define('STATUS_LEAVE', 'leave');

// Tolérances par défaut (en minutes)
define('DEFAULT_LATE_TOLERANCE', 15);
define('DEFAULT_EARLY_DEPARTURE_TOLERANCE', 15);
define('DEFAULT_WORK_HOURS_PER_DAY', 8); // Heures par jour
