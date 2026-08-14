<?php
/**
 * Modèle Journal d'audit (table `audit_logs`)
 * Fichier: models/AuditLog.php
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $user_name
 * @property string      $user_role
 * @property string      $action
 * @property string      $module
 * @property string|null $description
 * @property string      $ip_address
 * @property string      $user_agent
 * @property int|null    $record_id
 * @property string|null $old_values
 * @property string|null $new_values
 * @property string      $created_at
 */

declare(strict_types=1);

namespace App\Models;

use PDOException;

class AuditLog extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'audit_logs';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'module',
        'description',
        'ip_address',
        'user_agent',
        'record_id',
        'old_values',
        'new_values',
    ];

    /**
     * Enregistre une entrée dans le journal d'audit.
     *
     * Les informations sur l'utilisateur et la requête sont automatiquement
     * récupérées depuis la session et l'environnement serveur.
     *
     * @param string                    $action      Action effectuée (create, update, delete, login...).
     * @param string                    $module      Module concerné.
     * @param string                    $description Description détaillée.
     * @param int|null                  $recordId    ID de l'enregistrement concerné.
     * @param array<string, mixed>|null $oldValues   Anciennes valeurs (pour modifications).
     * @param array<string, mixed>|null $newValues   Nouvelles valeurs (pour modifications).
     * @return array<string, mixed>|null             L'entrée créée ou null en cas d'échec.
     */
    public function log(
        string $action,
        string $module,
        string $description,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?array {
        $session = $this->sessionUser();

        try {
            $sql = 'INSERT INTO `audit_logs`
                    (`user_id`, `user_name`, `user_role`, `action`, `module`, `description`,
                     `ip_address`, `user_agent`, `record_id`, `old_values`, `new_values`)
                     VALUES
                     (:user_id, :user_name, :user_role, :action, :module, :description,
                      :ip_address, :user_agent, :record_id, :old_values, :new_values)';

            $stmt = $this->pdo->prepare($sql);

            $stmt->bindValue(':user_id', $session['id'], $session['id'] === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':user_name', $session['name'], \PDO::PARAM_STR);
            $stmt->bindValue(':user_role', $session['role'], \PDO::PARAM_STR);
            $stmt->bindValue(':action', $action, \PDO::PARAM_STR);
            $stmt->bindValue(':module', $module, \PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, \PDO::PARAM_STR);
            $stmt->bindValue(':ip_address', $this->clientIp(), \PDO::PARAM_STR);
            $stmt->bindValue(':user_agent', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), \PDO::PARAM_STR);
            $stmt->bindValue(':record_id', $recordId, $recordId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':old_values', $this->encodeValues($oldValues), $oldValues === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            $stmt->bindValue(':new_values', $this->encodeValues($newValues), $newValues === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);

            $stmt->execute();

            $id = $this->pdo->lastInsertId();

            return $id ? $this->find((int) $id) : null;
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
            return null;
        }
    }

    /**
     * Récupère les entrées d'audit pour un module donné.
     *
     * @param string $module
     * @param int    $limit
     * @return array<int, array<string, mixed>>
     */
    public function getByModule(string $module, int $limit = 50): array
    {
        return $this->fetchAllRaw(
            'SELECT * FROM `audit_logs` WHERE `module` = :module ORDER BY `created_at` DESC LIMIT :limit',
            [':module' => $module, ':limit' => max(1, $limit)]
        );
    }

    /**
     * Récupère les entrées d'audit pour un utilisateur donné.
     *
     * @param int $userId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->fetchAllRaw(
            'SELECT * FROM `audit_logs` WHERE `user_id` = :user_id ORDER BY `created_at` DESC LIMIT :limit',
            [':user_id' => (int) $userId, ':limit' => max(1, $limit)]
        );
    }

    /**
     * Récupère les entrées d'audit les plus récentes.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 20): array
    {
        return $this->fetchAllRaw(
            'SELECT * FROM `audit_logs` ORDER BY `created_at` DESC LIMIT :limit',
            [':limit' => max(1, $limit)]
        );
    }

    /**
     * Journal d'audit paginé avec filtres.
     *
     * @param array<string, mixed> $filters Filtres disponibles : user_id, module, action, date_from, date_to.
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function paginateLogs(int $page, int $perPage, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]            = '`user_id` = :user_id';
            $params[':user_id'] = (int) $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $where[]           = '`module` = :module';
            $params[':module'] = $filters['module'];
        }
        if (!empty($filters['action'])) {
            $where[]           = '`action` = :action';
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['date_from'])) {
            $where[]               = 'DATE(`created_at`) >= :date_from';
            $params[':date_from']  = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]             = 'DATE(`created_at`) <= :date_to';
            $params[':date_to']  = $filters['date_to'];
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countRow = $this->fetchOneRaw("SELECT COUNT(*) AS cnt FROM `audit_logs`{$sqlWhere}", $params);
        $total    = $countRow !== null ? (int) $countRow['cnt'] : 0;

        $data = $this->fetchAllRaw(
            "SELECT * FROM `audit_logs`{$sqlWhere} ORDER BY `created_at` DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $data, 'total' => $total];
    }

    /**
     * Extrait les informations de l'utilisateur connecté depuis la session.
     *
     * @return array{id: int|null, name: string, role: string}
     */
    private function sessionUser(): array
    {
        $user = $_SESSION['user'] ?? $_SESSION ?? [];

        $id = $user['id'] ?? $user['user_id'] ?? null;

        $name = $user['name']
            ?? trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''))
            ?: (string) ($user['username'] ?? '');

        $role = $user['role'] ?? $user['role_name'] ?? '';

        return [
            'id'   => $id !== null ? (int) $id : null,
            'name' => trim((string) $name),
            'role' => (string) $role,
        ];
    }

    /**
     * Récupère l'adresse IP du client.
     */
    private function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 50);
    }

    /**
     * Encode un tableau de valeurs en JSON (ou null).
     *
     * @param array<string, mixed>|null $values
     */
    private function encodeValues(?array $values): ?string
    {
        if ($values === null) {
            return null;
        }

        $encoded = json_encode($values, JSON_UNESCAPED_UNICODE);

        return $encoded !== false ? $encoded : null;
    }
}
