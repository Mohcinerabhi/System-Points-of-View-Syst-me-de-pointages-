<?php
/**
 * Modèle Utilisateur (table `users`)
 * Fichier: models/User.php
 *
 * @property int         $id
 * @property int         $role_id
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $username
 * @property string      $email
 * @property string      $password_hash
 * @property string      $phone
 * @property string|null $avatar
 * @property string      $status
 * @property string|null $last_login
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class User extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'users';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'username',
        'email',
        'password_hash',
        'phone',
        'avatar',
        'status',
        'last_login',
    ];

    /**
     * @var string[]
     */
    protected array $hidden = [
        'password_hash',
    ];

    /**
     * ID du rôle administrateur (voir données initiales du schéma).
     */
    public const ROLE_ADMIN_ID = 1;

    /**
     * Récupère le rôle associé à l'utilisateur.
     *
     * @return array<string, mixed>|null
     */
    public function getRole(): ?array
    {
        $roleId = $this->role_id;
        if ($roleId === null) {
            return null;
        }

        return $this->fetchOneRaw(
            'SELECT * FROM `roles` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $roleId]
        );
    }

    /**
     * Retourne le nom complet de l'utilisateur.
     */
    public function getFullName(): string
    {
        return trim((string) $this->first_name . ' ' . (string) $this->last_name);
    }

    /**
     * Retourne l'URL de l'avatar (ou un avatar par défaut).
     */
    public function getAvatarUrl(): string
    {
        $avatar = (string) ($this->avatar ?? '');

        if ($avatar !== '') {
            if (preg_match('#^(https?:)?//#i', $avatar) === 1 || str_starts_with($avatar, '/')) {
                return $avatar;
            }
            $base = defined('UPLOAD_URL') ? rtrim((string) UPLOAD_URL, '/') : '/uploads';
            return $base . '/users/' . $avatar;
        }

        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        return $base . '/assets/images/default-avatar.png';
    }

    /**
     * Indique si l'utilisateur est administrateur.
     */
    public function isAdmin(): bool
    {
        if ((int) $this->role_id === self::ROLE_ADMIN_ID) {
            return true;
        }

        $role = $this->getRole();

        return $role !== null && strcasecmp((string) ($role['name'] ?? ''), 'Administrateur') === 0;
    }

    /**
     * Récupère les permissions liées au rôle de l'utilisateur.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getPermissions(): array
    {
        $roleId = $this->role_id;
        if ($roleId === null) {
            return [];
        }

        return $this->fetchAllRaw(
            'SELECT p.* FROM `permissions` p
             INNER JOIN `roles` r ON r.`id` = p.`role_id`
             WHERE p.`role_id` = :role_id
             ORDER BY p.`module` ASC',
            [':role_id' => (int) $roleId]
        );
    }

    /**
     * Met à jour la date de dernière connexion (maintenant).
     */
    public function updateLastLogin(): bool
    {
        $id = $this->getKey();
        if ($id === null) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE `users` SET `last_login` = NOW() WHERE `id` = :id'
            );
            $result = $stmt->execute([':id' => (int) $id]);

            if ($result) {
                $this->attributes['last_login'] = date('Y-m-d H:i:s');
            }

            return $result;
        } catch (\PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return false;
        }
    }

    /**
     * Recherche un utilisateur par nom d'utilisateur.
     */
    public function findByUsername(string $username): ?static
    {
        $row = $this->findBy('username', $username);
        if ($row === null) {
            return null;
        }

        $user = new static();
        $user->fill($row);

        return $user;
    }

    /**
     * Recherche un utilisateur par email.
     */
    public function findByEmail(string $email): ?static
    {
        $row = $this->findBy('email', $email);
        if ($row === null) {
            return null;
        }

        $user = new static();
        $user->fill($row);

        return $user;
    }

    /**
     * Vérifie les identifiants d'un utilisateur.
     *
     * Recherche l'utilisateur par nom d'utilisateur puis compare le mot de passe
     * en clair avec le hash stocké via password_verify().
     *
     * @return array<string, mixed>|null L'utilisateur si les identifiants sont valides, sinon null.
     */
    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->findBy('username', $username);
        if ($user === null) {
            return null;
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return $user;
    }

    /**
     * Charge le rôle et les permissions d'un utilisateur.
     *
     * @return array{role: string|null, permissions: array<int, array<string, mixed>>}
     */
    public function loadPermissions(int $userId): array
    {
        $user = $this->find((int) $userId);
        if ($user === null) {
            return ['role' => null, 'permissions' => []];
        }

        $roleId     = $user['role_id'] ?? null;
        $roleName   = null;
        $permissions = [];

        if ($roleId !== null) {
            $role = $this->fetchOneRaw(
                'SELECT * FROM `roles` WHERE `id` = :id LIMIT 1',
                [':id' => (int) $roleId]
            );
            if ($role !== null) {
                $roleName = $role['name'] ?? null;
            }

            $permissions = $this->fetchAllRaw(
                'SELECT * FROM `permissions` WHERE `role_id` = :role_id ORDER BY `module` ASC',
                [':role_id' => (int) $roleId]
            );
        }

        return ['role' => $roleName, 'permissions' => $permissions];
    }
}
