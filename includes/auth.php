<?php
/**
 * Authentification et autorisation
 * Fichier: includes/auth.php
 */

declare(strict_types=1);

namespace App\Includes;

use App\Config\Constants;
use App\Config\Database;
use App\Helpers\logAudit;
use App\Helpers\flash;
use App\Helpers\redirect;
use PDOException;

/**
 * Classe d'authentification
 * Gère la connexion, déconnexion et les permissions
 */
class Auth
{
    /**
     * Instance de session
     *
     * @var Session
     */
    private Session $session;

    /**
     * Instance de base de données
     *
     * @var Database
     */
    private Database $db;

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->session = new Session();
        $this->db      = Database::getInstance();
    }

    /**
     * Tente de connecter un utilisateur
     *
     * @param string $username Nom d'utilisateur ou email
     * @param string $password Mot de passe en clair
     * @return bool Succès de la connexion
     */
    public function login(string $username, string $password): bool
    {
        try {
            // Rechercher l'utilisateur par nom d'utilisateur ou email
            $sql = "SELECT id, username, email, password, role, permissions, is_active 
                    FROM users 
                    WHERE (username = :identifier OR email = :identifier) 
                    AND is_active = 1 
                    LIMIT 1";

            $user = $this->db->fetchOne($sql, [':identifier' => $username]);

            if (!$user || !password_verify($password, $user['password'])) {
                return false;
            }

            // Stocker les données utilisateur en session
            $this->session->setUser($user);
            $this->session->generateCSRFToken();

            // Journal d'audit
            logAudit('login', 'auth', "Connexion de l'utilisateur {$user['username']}", $user['id']);

            return true;
        } catch (PDOException $e) {
            error_log("Erreur lors de la connexion: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Déconnecte l'utilisateur
     *
     * @return void
     */
    public function logout(): void
    {
        $user = $this->session->getUser();
        
        if ($user) {
            logAudit('logout', 'auth', "Déconnexion de l'utilisateur {$user['username']}", $user['id'] ?? null);
        }

        $this->session->destroy();
    }

    /**
     * Récupère l'utilisateur connecté
     *
     * @return array|null
     */
    public function getCurrentUser(): ?array
    {
        return $this->session->getUser();
    }

    /**
     * Vérifie si l'utilisateur est connecté
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->session->isLoggedIn();
    }

    /**
     * Exige que l'utilisateur soit connecté, sinon redirige vers la page de connexion
     *
     * @param string|null $redirectUrl URL de redirection après connexion
     * @return void
     */
    public function requireAuth(?string $redirectUrl = null): void
    {
        if (!$this->isLoggedIn()) {
            $url = $redirectUrl ?? url('login.php');
            flash('error', 'Vous devez être connecté pour accéder à cette page.');
            redirect($url);
        }

        // Rafraîchir la session pour éviter le timeout
        $this->session->refresh();
    }

    /**
     * Vérifie si l'utilisateur a la permission requise
     *
     * @param string $module Module concerné (ex: 'employees', 'attendance')
     * @param string $action Action concernée (ex: 'view', 'create', 'edit', 'delete')
     * @return bool
     */
    public function hasPermission(string $module, string $action): bool
    {
        return $this->session->hasPermission($module, $action);
    }

    /**
     * Exige une permission spécifique, sinon affiche une erreur 403
     *
     * @param string $module Module concerné
     * @param string $action Action concernée
     * @return void
     */
    public function requirePermission(string $module, string $action): void
    {
        $this->requireAuth();

        if (!$this->hasPermission($module, $action)) {
            http_response_code(403);
            flash('error', 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.');
            redirect(url('403.php'));
        }
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     *
     * @param string $role Rôle à vérifier
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        $user = $this->session->getUser();
        return $user !== null && $user['role'] === $role;
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Constants::ROLE_ADMIN);
    }

    /**
     * Vérifie si l'utilisateur est manager
     *
     * @return bool
     */
    public function isManager(): bool
    {
        return $this->hasRole(Constants::ROLE_MANAGER);
    }

    /**
     * Récupère le rôle de l'utilisateur connecté
     *
     * @return string|null
     */
    public function getRole(): ?string
    {
        return $this->session->getRole();
    }

    /**
     * Récupère les permissions de l'utilisateur connecté
     *
     * @return array
     */
    public function getPermissions(): array
    {
        $user = $this->session->getUser();
        return $user['permissions'] ?? [];
    }

    /**
     * Récupère le token CSRF
     *
     * @return string
     */
    public function getCSRFToken(): string
    {
        return $this->session->getCSRFToken();
    }

    /**
     * Régénère le token CSRF
     *
     * @return string
     */
    public function regenerateCSRFToken(): string
    {
        return $this->session->regenerateCSRFToken();
    }

    /**
     * Valide un token CSRF
     *
     * @param string $token Token à valider
     * @return bool
     */
    public function validateCSRFToken(string $token): bool
    {
        return $this->session->validateCSRFToken($token);
    }
}
