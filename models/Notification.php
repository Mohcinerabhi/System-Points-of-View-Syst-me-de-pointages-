<?php
/**
 * Modèle Notification (table `notifications`)
 * Fichier: models/Notification.php
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $type
 * @property string      $title
 * @property string|null $message
 * @property string|null $data
 * @property int         $is_read
 * @property string      $created_at
 */

declare(strict_types=1);

namespace App\Models;

class Notification extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'notifications';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
    ];

    /**
     * Types de notifications.
     */
    public const TYPE_MISSING_PUNCH = 'missing_punch';
    public const TYPE_OVERTIME      = 'overtime';
    public const TYPE_DELAY         = 'delay';
    public const TYPE_INFO          = 'info';

    /**
     * Crée une notification.
     *
     * @param array<string, mixed> $data Données de notification : user_id, type, title, message, data, is_read
     * @return int ID de la notification créée
     */
    public function create(array $data): int
    {
        return parent::create($data);
    }

    /**
     * Envoie une notification avec des paramètres individuels.
     *
     * @param string                    $type      Type de notification.
     * @param string                    $title     Titre.
     * @param string|null               $message   Message détaillé.
     * @param int|null                  $userId    Utilisateur cible (null = tous les managers).
     * @param array<string, mixed>|null $data      Données supplémentaires.
     * @return int ID de la notification créée
     */
    public function send(string $type, string $title, ?string $message = null, ?int $userId = null, ?array $data = null): int
    {
        $payload = [
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
            'is_read' => 0,
        ];

        return $this->create($payload);
    }

    /**
     * Récupère les notifications non lues pour un utilisateur.
     *
     * @param int|null $userId
     * @param int      $limit
     * @return array<int, array<string, mixed>>
     */
    public function getUnread(?int $userId, int $limit = 20): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `is_read` = 0";

        $params = [];
        if ($userId !== null) {
            $sql .= " AND (`user_id` = :user_id OR `user_id` IS NULL)";
            $params[':user_id'] = $userId;
        }

        $sql .= " ORDER BY `created_at` DESC LIMIT :limit";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marque une notification comme lue.
     */
    public function markAsRead(int $id): bool
    {
        return $this->update($id, ['is_read' => 1]) !== null;
    }

    /**
     * Marque toutes les notifications comme lues pour un utilisateur.
     */
    public function markAllAsRead(?int $userId): bool
    {
        $sql = "UPDATE `{$this->table}` SET `is_read` = 1 WHERE `is_read` = 0";

        $params = [];
        if ($userId !== null) {
            $sql .= " AND (`user_id` = :user_id OR `user_id` IS NULL)";
            $params[':user_id'] = $userId;
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }

        return $stmt->execute();
    }

    /**
     * Récupère les managers (users avec rôle manager/rh/admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getManagers(): array
    {
        return $this->fetchAllRaw(
            "SELECT u.`id`, u.`username`, u.`first_name`, u.`last_name`, r.`name` AS `role`
             FROM `users` u
             JOIN `roles` r ON r.`id` = u.`role_id`
             WHERE r.`name` IN ('Administrateur', 'RH', 'manager')
                OR u.`username` = 'admin'
             ORDER BY r.`name` DESC, u.`username` ASC"
        );
    }

    /**
     * Vérifie si une notification existe déjà pour les mêmes critères
     * afin d'éviter les doublons dans une fenêtre de temps.
     *
     * @return bool
     */
    public function existsRecent(string $type, ?int $userId, array $data, int $withinMinutes = 60): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$this->table}` WHERE `type` = :type AND `created_at` >= NOW() - INTERVAL :minutes MINUTE";

        $params = [':type' => $type, ':minutes' => max(1, $withinMinutes)];

        if ($userId !== null) {
            $sql .= " AND `user_id` = :user_id";
            $params[':user_id'] = $userId;
        }

        if (!empty($data)) {
            $sql .= " AND `data` = :data";
            $params[':data'] = json_encode($data, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}
