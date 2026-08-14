<?php
/**
 * Fonctions utilitaires de l'application
 * Fichier: helpers/functions.php
 */

declare(strict_types=1);

namespace App\Helpers;

use App\Helpers\Session;
use App\Core\Database;
use PDOException;
use RuntimeException;

/**
 * Redirige vers une URL
 *
 * @param string $url URL de destination
 * @return void
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Génère une URL relative à partir d'un chemin
 *
 * @param string $path Chemin de l'URL
 * @return string URL complète
 */
function url(string $path = ''): string
{
    $baseUrl = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');
    
    return $path ? $baseUrl . '/' . $path : $baseUrl;
}

/**
 * Génère l'URL d'un asset (fichier statique)
 *
 * @param string $path Chemin de l'asset
 * @return string URL complète de l'asset
 */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/**
 * Échappe les caractères HTML pour prévenir les attaques XSS
 *
 * @param string $string Chaîne à échapper
 * @return string Chaîne échappée
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Récupère une ancienne valeur de formulaire (après soumission avec erreurs)
 *
 * @param string $key     Clé de la valeur
 * @param mixed  $default Valeur par défaut
 * @return mixed
 */
function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['old'][$key] ?? $default;
}

/**
 * Stocke un message flash dans la session
 *
 * @param string $type    Type de message (success, error, warning, info)
 * @param string $message Message à afficher
 * @return void
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Récupère et supprime les messages flash
 *
 * @return array
 */
function getFlashMessages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Vérifie s'il y a des messages flash
 *
 * @return bool
 */
function hasFlashMessages(): bool
{
    return !empty($_SESSION['flash']);
}

/**
 * Retourne la classe CSS active pour un lien de navigation
 *
 * @param string $path Chemin à vérifier
 * @return string Classe CSS
 */
function isActive(string $path): string
{
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $path = rtrim($path, '/');
    
    if ($path === '' || $path === '/') {
        return $currentPath === '/' ? 'active' : '';
    }

    return str_starts_with($currentPath, $path) ? 'active' : '';
}

/**
 * Formate une date
 *
 * @param string      $date    Date à formater (Y-m-d ou DateTime)
 * @param string|null $format  Format de sortie (null = format par défaut)
 * @return string Date formatée
 */
function formatDate(string $date, ?string $format = null): string
{
    if ($date instanceof \DateTime) {
        $timestamp = $date->getTimestamp();
    } else {
        $timestamp = strtotime($date);
    }

    if (!$timestamp) {
        return '';
    }

    $format = $format ?? 'd/m/Y';
    return date($format, $timestamp);
}

/**
 * Formate une heure
 *
 * @param string      $time    Heure à formater (H:i:s)
 * @param string|null $format  Format de sortie (null = format par défaut)
 * @return string Heure formatée
 */
function formatTime(string $time, ?string $format = null): string
{
    $timestamp = strtotime($time);
    
    if (!$timestamp) {
        return '';
    }

    $format = $format ?? 'H:i';
    return date($format, $timestamp);
}

/**
 * Formate une date et heure
 *
 * @param string      $datetime Date et heure à formater
 * @param string|null $format   Format de sortie (null = format par défaut)
 * @return string Date et heure formatées
 */
function formatDateTime(string $datetime, ?string $format = null): string
{
    if ($datetime instanceof \DateTime) {
        $timestamp = $datetime->getTimestamp();
    } else {
        $timestamp = strtotime($datetime);
    }

    if (!$timestamp) {
        return '';
    }

    $format = $format ?? 'd/m/Y H:i';
    return date($format, $timestamp);
}

/**
 * Calcule le nombre de minutes travaillées entre deux heures
 *
 * @param string $startTime Heure de début (H:i:s)
 * @param string $endTime   Heure de fin (H:i:s)
 * @return int Nombre de minutes
 */
function calculateWorkMinutes(string $startTime, string $endTime): int
{
    $start = strtotime($startTime);
    $end   = strtotime($endTime);
    
    if (!$start || !$end || $end < $start) {
        return 0;
    }

    return (int) round(($end - $start) / 60);
}

/**
 * Calcule le nombre de minutes de retard
 *
 * @param string $startTime Heure de début prévue (H:i:s)
 * @param int    $tolerance Tolérance en minutes (défaut: 15)
 * @return int Nombre de minutes de retard
 */
function getLateMinutes(string $startTime, int $tolerance = Constants::DEFAULT_LATE_TOLERANCE): int
{
    $expected = strtotime($startTime);
    $now      = time();
    
    if (!$expected) {
        return 0;
    }

    $diff = $now - $expected;
    
    if ($diff <= $tolerance * 60) {
        return 0;
    }

    return (int) round($diff / 60);
}

/**
 * Calcule le nombre de minutes de départ anticipé
 *
 * @param string $endTime   Heure de fin prévue (H:i:s)
 * @param int    $tolerance Tolérance en minutes (défaut: 15)
 * @return int Nombre de minutes de départ anticipé
 */
function getEarlyDepartureMinutes(string $endTime, int $tolerance = Constants::DEFAULT_EARLY_DEPARTURE_TOLERANCE): int
{
    $expected = strtotime($endTime);
    $now      = time();
    
    if (!$expected) {
        return 0;
    }

    $diff = $expected - $now;
    
    if ($diff <= $tolerance * 60) {
        return 0;
    }

    return (int) round($diff / 60);
}

/**
 * Calcule le nombre d'heures supplémentaires
 *
 * @param int $worked    Minutes travaillées
 * @param int $required  Minutes requises
 * @return int Nombre d'heures supplémentaires
 */
function getOvertimeMinutes(int $worked, int $required): int
{
    if ($worked <= $required) {
        return 0;
    }
    return $worked - $required;
}

/**
 * Calcule le nombre de minutes manquantes
 *
 * @param int $worked    Minutes travaillées
 * @param int $required  Minutes requises
 * @return int Nombre de minutes manquantes
 */
function getMissingMinutes(int $worked, int $required): int
{
    if ($worked >= $required) {
        return 0;
    }
    return $required - $worked;
}

/**
 * Génère un token CSRF aléatoire
 *
 * @return string Token CSRF
 */
function generateCSRFToken(): string
{
    return bin2hex(random_bytes(Constants::CSRF_TOKEN_LENGTH / 2));
}

/**
 * Valide un token CSRF
 *
 * @param string $token Token à valider
 * @return bool
 */
function validateCSRFToken(string $token): bool
{
    $session = new Session();
    return $session->validateCSRFToken($token);
}

/**
 * Upload un fichier
 *
 * @param array      $file         Tableau $_FILES['field_name']
 * @param string     $path         Chemin de destination (relatif à UPLOAD_PATH)
 * @param array      $allowedTypes Types de fichiers autorisés (ex: ['jpg', 'png'])
 * @param int|null   $maxSize      Taille maximale en octets (null = MAX_FILE_SIZE)
 * @return array Résultat ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadFile(array $file, string $path, array $allowedTypes, ?int $maxSize = null): array
{
    $maxSize = $maxSize ?? Constants::MAX_FILE_SIZE;
    
    // Vérifier les erreurs d'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille maximale autorisée par le serveur.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille maximale autorisée par le formulaire.',
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement uploadé.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été uploadé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le dossier temporaire est manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque.',
            UPLOAD_ERR_EXTENSION   => 'Une extension PHP a arrêté l\'upload du fichier.'
        ];
        
        $errorMessage = $errors[$file['error']] ?? 'Erreur inconnue lors de l\'upload.';
        return ['success' => false, 'message' => $errorMessage, 'filename' => null];
    }

    // Vérifier la taille
    if ($file['size'] > $maxSize) {
        $maxSizeMB = $maxSize / (1024 * 1024);
        return [
            'success' => false,
            'message' => "Le fichier dépasse la taille maximale autorisée de {$maxSizeMB} Mo.",
            'filename' => null
        ];
    }

    // Vérifier le type de fichier
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes, true)) {
        $allowedList = implode(', ', $allowedTypes);
        return [
            'success' => false,
            'message' => "Type de fichier non autorisé. Types acceptés: {$allowedList}",
            'filename' => null
        ];
    }

    // Créer le dossier de destination s'il n'existe pas
    $destinationDir = UPLOAD_PATH . ltrim($path, '/');
    
    if (!is_dir($destinationDir)) {
        if (!mkdir($destinationDir, 0755, true) && !is_dir($destinationDir)) {
            return [
                'success' => false,
                'message' => 'Impossible de créer le dossier de destination.',
                'filename' => null
            ];
        }
    }

    // Générer un nom de fichier unique
    $filename = uniqid() . '_' . time() . '.' . $fileExtension;
    $destination = $destinationDir . '/' . $filename;

    // Déplacer le fichier uploadé
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => false,
            'message' => 'Échec du déplacement du fichier uploadé.',
            'filename' => null
        ];
    }

    // Construire le chemin relatif pour la base de données
    $relativePath = ltrim($path, '/') . '/' . $filename;

    return [
        'success' => true,
        'message' => 'Fichier uploadé avec succès.',
        'filename' => $relativePath
    ];
}

/**
 * Supprime un fichier uploadé
 *
 * @param string $filepath Chemin relatif du fichier
 * @return bool
 */
function deleteUploadedFile(string $filepath): bool
{
    $fullPath = UPLOAD_PATH . $filepath;
    
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    
    return false;
}

/**
 * Génère la pagination
 *
 * @param int    $currentPage Page actuelle
 * @param int    $totalItems  Nombre total d'éléments
 * @param int    $itemsPerPage Nombre d'éléments par page
 * @param string $baseUrl     URL de base pour les liens
 * @return array Données de pagination
 */
function paginate(int $currentPage, int $totalItems, int $itemsPerPage, string $baseUrl = ''): array
{
    $totalPages = (int) ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset     = ($currentPage - 1) * $itemsPerPage;
    $hasNext    = $currentPage < $totalPages;
    $hasPrev    = $currentPage > 1;

    $pages = [];
    $range = 2; // Nombre de pages à afficher de chaque côté de la page courante

    for ($i = max(1, $currentPage - $range); $i <= min($totalPages, $currentPage + $range); $i++) {
        $pages[] = [
            'page'     => $i,
            'url'      => $baseUrl ? $baseUrl . '?page=' . $i : '?page=' . $i,
            'isActive' => $i === $currentPage
        ];
    }

    return [
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'totalItems'  => $totalItems,
        'itemsPerPage' => $itemsPerPage,
        'offset'      => $offset,
        'hasNext'     => $hasNext,
        'hasPrev'     => $hasPrev,
        'nextUrl'     => $hasNext ? ($baseUrl ? $baseUrl . '?page=' . ($currentPage + 1) : '?page=' . ($currentPage + 1)) : null,
        'prevUrl'     => $hasPrev ? ($baseUrl ? $baseUrl . '?page=' . ($currentPage - 1) : '?page=' . ($currentPage - 1)) : null,
        'pages'       => $pages,
        'startItem'   => $totalItems > 0 ? $offset + 1 : 0,
        'endItem'     => min($offset + $itemsPerPage, $totalItems)
    ];
}

/**
 * Retourne une réponse JSON et arrête l'exécution
 *
 * @param mixed  $data       Données à retourner
 * @param int    $statusCode Code de statut HTTP
 * @param array  $headers    En-têtes supplémentaires
 * @return void
 */
function jsonResponse(mixed $data, int $statusCode = 200, array $headers = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    foreach ($headers as $key => $value) {
        header("{$key}: {$value}");
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Enregistre une entrée dans le journal d'audit
 *
 * @param string      $action      Action effectuée (create, update, delete, view, login, logout)
 * @param string      $module      Module concerné
 * @param string      $description Description de l'action
 * @param int|null    $recordId    ID de l'enregistrement concerné
 * @param array|null  $oldValues   Anciennes valeurs (pour modification)
 * @param array|null  $newValues   Nouvelles valeurs (pour modification)
 * @return bool Succès de l'opération
 */
function logAudit(string $action, string $module, string $description, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): bool
{
    try {
         $db = Database::connection();
        $session = new Session();
        $user = $session->getUser();

        $sql = "INSERT INTO audit_logs 
                (user_id, action, module, description, record_id, old_values, new_values, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :module, :description, :record_id, :old_values, :new_values, :ip_address, :user_agent, NOW())";

        $params = [
            ':user_id'    => $user['id'] ?? null,
            ':action'     => $action,
            ':module'     => $module,
            ':description' => $description,
            ':record_id'  => $recordId,
            ':old_values' => $oldValues ? json_encode($oldValues) : null,
            ':new_values' => $newValues ? json_encode($newValues) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $db->query($sql, $params);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur d'audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Vérifie si une date est un week-end
 *
 * @param string $date Date à vérifier (Y-m-d ou DateTime)
 * @return bool
 */
function isWeekend(string $date): bool
{
    if ($date instanceof \DateTime) {
        $dayOfWeek = (int) $date->format('N');
    } else {
        $dayOfWeek = (int) date('N', strtotime($date));
    }

    return $dayOfWeek >= 6;
}

/**
 * Récupère le nom du jour en français
 *
 * @param string $date Date (Y-m-d ou DateTime)
 * @return string Nom du jour
 */
function getDayName(string $date): string
{
    if ($date instanceof \DateTime) {
        $dayOfWeek = (int) $date->format('w');
    } else {
        $dayOfWeek = (int) date('w', strtotime($date));
    }

    $days = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi'
    ];

    return $days[$dayOfWeek] ?? '';
}

/**
 * Récupère le nombre de jours dans un mois
 *
 * @param int $month Mois (1-12)
 * @param int $year  Année
 * @return int Nombre de jours
 */
function getDaysInMonth(int $month, int $year): int
{
    return (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);
}

/**
 * Détermine le statut de pointage et retourne une classe CSS pour le badge
 *
 * @param array $dateData Données de pointage ['check_in' => ?, 'check_out' => ?, 'expected_start' => ?, 'expected_end' => ?]
 * @return array ['status' => string, 'class' => string, 'label' => string]
 */
function getAttendanceStatus(array $dateData): array
{
    $checkIn  = $dateData['check_in'] ?? null;
    $checkOut = $dateData['check_out'] ?? null;
    $expectedStart = $dateData['expected_start'] ?? null;
    $expectedEnd   = $dateData['expected_end'] ?? null;

    // Absent
    if (!$checkIn && !$checkOut) {
        return [
            'status' => Constants::STATUS_ABSENT,
            'class'  => 'badge-danger',
            'label'  => 'Absent'
        ];
    }

    // Départ anticipé
    if ($checkOut && $expectedEnd) {
        $earlyMinutes = getEarlyDepartureMinutes($checkOut, 0); // Sans tolérance
        if ($earlyMinutes > 0) {
            return [
                'status' => Constants::STATUS_EARLY_DEPARTURE,
                'class'  => 'badge-warning',
                'label'  => 'Départ anticipé'
            ];
        }
    }

    // Retard
    if ($checkIn && $expectedStart) {
        $lateMinutes = getLateMinutes($checkIn, 0); // Sans tolérance
        if ($lateMinutes > 0) {
            return [
                'status' => Constants::STATUS_LATE,
                'class'  => 'badge-warning',
                'label'  => 'Retard'
            ];
        }
    }

    // Demi-journée (présent mais pas complet)
    if ($checkIn && $checkOut && $expectedStart && $expectedEnd) {
        $workedMinutes = calculateWorkMinutes($checkIn, $checkOut);
        $requiredMinutes = calculateWorkMinutes($expectedStart, $expectedEnd);
        
        if ($workedMinutes < $requiredMinutes / 2) {
            return [
                'status' => Constants::STATUS_HALF_DAY,
                'class'  => 'badge-info',
                'label'  => 'Demi-journée'
            ];
        }
    }

    // Présent
    return [
        'status' => Constants::STATUS_PRESENT,
        'class'  => 'badge-success',
        'label'  => 'Présent'
    ];
}

/**
 * Récupère le chemin d'un upload
 *
 * @param string $path Chemin relatif
 * @return string URL complète
 */
function getUploadUrl(string $path): string
{
    return UPLOAD_URL . ltrim($path, '/');
}

/**
 * Vérifie si une chaîne commence par un préfixe (compatible PHP < 8.0)
 *
 * @param string $haystack Chaîne à vérifier
 * @param string $needle   Préfixe recherché
 * @return bool
 */
function str_starts_with(string $haystack, string $needle): bool
{
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

/**
 * Sécurise une chaîne pour l'affichage
 *
 * @param string|null $string Chaîne à sécuriser
 * @return string
 */
function safeString(?string $string): string
{
    return e($string ?? '');
}

/**
 * Formate un nombre de minutes en heures:minutes
 *
 * @param int $minutes Nombre de minutes
 * @return string Durée formatée (ex: "02:30")
 */
function formatDuration(int $minutes): string
{
    $hours   = (int) floor($minutes / 60);
    $minutes = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $minutes);
}

/**
 * Calcule l'âge à partir d'une date de naissance
 *
 * @param string $birthDate Date de naissance (Y-m-d)
 * @return int Âge
 */
function calculateAge(string $birthDate): int
{
    $birth = new \DateTime($birthDate);
    $now   = new \DateTime();
    return $now->diff($birth)->y;
}
