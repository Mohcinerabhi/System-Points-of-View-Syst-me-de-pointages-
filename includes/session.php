<?php
/**
 * Gestion des sessions
 * Fichier: includes/session.php
 */

declare(strict_types=1);

namespace App\Includes;

use App\Config\Constants;

/**
 * Classe de gestion des sessions
 * Gère l'authentification, les permissions et les tokens CSRF
 */
class Session
{
    /**
     * Nom de la session
     *
     * @var string
     */
    private const SESSION_NAME = 'attendance_session';

    /**
     * Clé pour stocker les données utilisateur en session
     *
     * @var string
     */
    private const USER_KEY = 'user';

    /**
     * Clé pour stocker le temps de connexion
     *
     * @var string
     */
    private const LOGIN_TIME_KEY = 'login_time';

    /**
     * Clé pour stocker le token CSRF
     *
     * @var string
     */
    private const CSRF_TOKEN_KEY = 'csrf_token';

    /**
     * Démarre la session
     *
     * @return void
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::SESSION_NAME);
            session_set_cookie_params([
                'lifetime' => Constants::SESSION_TIMEOUT,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
            $this->checkSessionTimeout();
        }
    }

    /**
     * Vérifie si la session a expiré et la détruit si c'est le cas
     *
     * @return void
     */
    private function checkSessionTimeout(): void
    {
        if (isset($_SESSION[self::LOGIN_TIME_KEY])) {
            $elapsed = time() - $_SESSION[self::LOGIN_TIME_KEY];
            if ($elapsed > Constants::SESSION_TIMEOUT) {
                $this->destroy();
            }
        }
    }

    /**
     * Récupère une valeur de la session
     *
     * @param string $key      Clé de la valeur
     * @param mixed  $default  Valeur par défaut si la clé n'existe pas
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Définit une valeur dans la session
     *
     * @param string $key   Clé de la valeur
     * @param mixed  $value Valeur à stocker
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Supprime une valeur de la session
     *
     * @param string $key Clé de la valeur à supprimer
     * @return void
     */
    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Détruit la session et la régénère
     *
     * @return void
     */
    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax'
                ]
            );
        }
        session_destroy();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION[self::USER_KEY]) && !empty($_SESSION[self::USER_KEY]);
    }

    /**
     * Définit l'utilisateur connecté
     *
     * @param array $user Données de l'utilisateur
     * @return void
     */
    public function setUser(array $user): void
    {
        // Ne pas stocker le mot de passe en session
        unset($user['password']);
        $_SESSION[self::USER_KEY] = $user;
        $_SESSION[self::LOGIN_TIME_KEY] = time();
    }

    /**
     * Récupère les données de l'utilisateur connecté
     *
     * @return array|null
     */
    public function getUser(): ?array
    {
        return $_SESSION[self::USER_KEY] ?? null;
    }

    /**
     * Récupère le rôle de l'utilisateur connecté
     *
     * @return string|null
     */
    public function getRole(): ?string
    {
        $user = $this->getUser();
        return $user['role'] ?? null;
    }

    /**
     * Vérifie si l'utilisateur a une permission spécifique
     *
     * @param string $module   Module concerné (ex: 'employees', 'attendance')
     * @param string $action   Action concernée (ex: 'view', 'create', 'edit', 'delete')
     * @return bool
     */
    public function hasPermission(string $module, string $action): bool
    {
        $user = $this->getUser();
        
        if (!$user) {
            return false;
        }

        // L'admin a toutes les permissions
        if ($user['role'] === Constants::ROLE_ADMIN) {
            return true;
        }

        // Récupérer les permissions de l'utilisateur
        $permissions = $user['permissions'] ?? [];

        // Vérifier la permission spécifique
        $requiredPermission = "{$module}.{$action}";
        
        // Vérifier permission exacte
        if (in_array($requiredPermission, $permissions, true)) {
            return true;
        }

        // Vérifier permission générique (ex: 'employees.*')
        $wildcardPermission = "{$module}.*";
        if (in_array($wildcardPermission, $permissions, true)) {
            return true;
        }

        // Permission de vue par défaut
        if ($action === 'view' && in_array("{$module}.view", $permissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Génère un token CSRF unique
     *
     * @return string
     */
    public function generateCSRFToken(): string
    {
        if (empty($_SESSION[self::CSRF_TOKEN_KEY])) {
            $_SESSION[self::CSRF_TOKEN_KEY] = bin2hex(random_bytes(Constants::CSRF_TOKEN_LENGTH / 2));
        }
        return $_SESSION[self::CSRF_TOKEN_KEY];
    }

    /**
     * Récupère le token CSRF actuel (génère un nouveau si nécessaire)
     *
     * @return string
     */
    public function getCSRFToken(): string
    {
        return $this->generateCSRFToken();
    }

    /**
     * Régénère le token CSRF (utile après une action sensible)
     *
     * @return string
     */
    public function regenerateCSRFToken(): string
    {
        unset($_SESSION[self::CSRF_TOKEN_KEY]);
        return $this->generateCSRFToken();
    }

    /**
     * Valide un token CSRF
     *
     * @param string $token Token à valider
     * @return bool
     */
    public function validateCSRFToken(string $token): bool
    {
        if (!isset($_SESSION[self::CSRF_TOKEN_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::CSRF_TOKEN_KEY], $token);
    }

    /**
     * Rafraîchit le temps de dernière activité (évite le timeout)
     *
     * @return void
     */
    public function refresh(): void
    {
        if ($this->isLoggedIn()) {
            $_SESSION[self::LOGIN_TIME_KEY] = time();
        }
    }

    /**
     * Récupère le temps écoulé depuis la connexion (en secondes)
     *
     * @return int
     */
    public function getLoginTime(): int
    {
        return $_SESSION[self::LOGIN_TIME_KEY] ?? 0;
    }

    /**
     * Récupère la durée de la session en secondes
     *
     * @return int
     */
    public function getSessionDuration(): int
    {
        if (!$this->isLoggedIn()) {
            return 0;
        }
        return time() - $this->getLoginTime();
    }
}
