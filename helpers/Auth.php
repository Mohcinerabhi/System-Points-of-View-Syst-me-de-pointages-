<?php
namespace App\Helpers;

/**
 * Authentification et gestion des permissions.
 *
 * Les permissions sont stockées en session sous la forme :
 *   $_SESSION['permissions'][$module][$action] = bool
 */
class Auth
{
    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function user(): ?array
    {
        return Session::get('user');
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function name(): string
    {
        $user = self::user();
        if (!$user) {
            return '';
        }
        return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    }

    /**
     * Vérifie si l'utilisateur a la permission demandée.
     */
    public static function hasPermission(string $module, string $action = 'view'): bool
    {
        if (!self::check()) {
            return false;
        }
        // L'administrateur a tous les droits.
        if (self::role() === 'Administrateur') {
            return true;
        }
        $permissions = Session::get('permissions', []);
        return !empty($permissions[$module][$action]);
    }

    /**
     * Redirige vers la page de connexion si non authentifié.
     */
    public static function requireAuth(string $loginUrl = 'index.php?controller=auth&action=login'): void
    {
        if (!self::check()) {
            Session::set('flash_error', 'Veuillez vous connecter pour accéder à cette page.');
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /**
     * Redirige avec une erreur 403 si la permission est absente.
     */
    public static function requirePermission(string $module, string $action = 'view'): void
    {
        self::requireAuth();
        if (!self::hasPermission($module, $action)) {
            Session::set('flash_error', 'Vous n\'avez pas la permission d\'accéder à ce module.');
            header('Location: index.php?controller=dashboard&action=index');
            exit;
        }
    }
}
