<?php
/**
 * Modèle Terminal (table `terminals`)
 * Fichier: models/Terminal.php
 *
 * @property int         $id
 * @property string      $name
 * @property string      $ip_address
 * @property int         $port
 * @property string      $username
 * @property string      $password_hash
 * @property string|null $serial_number
 * @property string      $model
 * @property string      $connection_status
 * @property string|null $last_connection_test
 * @property string|null $last_sync
 * @property int         $sync_enabled
 * @property string      $status
 * @property string|null $notes
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

use PDOException;

class Terminal extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'terminals';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'ip_address',
        'port',
        'username',
        'password_hash',
        'serial_number',
        'model',
        'mac_address',
        'protocol',
        'network_info',
        'rtsp_port',
        'isup_server_ip',
        'isup_server_port',
        'isup_device_id',
        'connection_status',
        'last_connection_test',
        'last_sync',
        'device_time',
        'timezone',
        'sync_enabled',
        'status',
        'notes',
    ];

    /**
     * @var string[]
     */
    protected array $hidden = [
        'password_hash',
    ];

    /**
     * Méthode de chiffrement utilisée pour les mots de passe des terminaux.
     */
    private const CIPHER = 'AES-256-CBC';

    /**
     * Chiffre un mot de passe en clair pour stockage (réversible).
     *
     * Les mots de passe de terminaux doivent être réversibles car ils sont
      * nécessaires pour l'authentification ISUP auprès de l'appareil.
     */
    public static function encryptPassword(string $plain): string
    {
        $key    = self::encryptionKey();
        $ivLen  = openssl_cipher_iv_length(self::CIPHER) ?: 16;
        $iv     = random_bytes($ivLen);
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($cipher === false) {
            return $plain;
        }

        return base64_encode($iv . $cipher);
    }

    /**
     * Déchiffre et retourne le mot de passe en clair du terminal.
     *
     * Retourne la valeur stockée telle quelle si le déchiffrement échoue
     * (compatibilité avec d'anciennes valeurs non chiffrées).
     */
    public function getPassword(): string
    {
        $stored = (string) ($this->password_hash ?? '');
        if ($stored === '') {
            return '';
        }

        return self::decryptPassword($stored);
    }

    /**
     * Déchiffre un mot de passe stocké.
     */
    public static function decryptPassword(string $stored): string
    {
        if ($stored === '') {
            return '';
        }

        $decoded = base64_decode($stored, true);
        if ($decoded === false) {
            return $stored;
        }

        $ivLen = openssl_cipher_iv_length(self::CIPHER) ?: 16;
        if (strlen($decoded) <= $ivLen) {
            return $stored;
        }

        $iv     = substr($decoded, 0, $ivLen);
        $cipher = substr($decoded, $ivLen);
        $plain  = openssl_decrypt($cipher, self::CIPHER, self::encryptionKey(), OPENSSL_RAW_DATA, $iv);

        return $plain !== false ? $plain : $stored;
    }

    /**
     * Teste la connexion TCP au terminal et met à jour son statut.
     *
     * @param int $timeout Délai maximum en secondes.
     * @return bool         Vrai si la connexion a réussi.
     */
    public function testConnection(int $timeout = 5): bool
    {
        $ip = (string) ($this->ip_address ?? '');
        if ($ip === '') {
            return false;
        }

        $protocol = strtoupper((string) ($this->protocol ?? ''));
        if ($protocol === 'ISUP') {
            $port = (int) ($this->isup_server_port ?? 8000);
        } else {
            $port = (int) ($this->port ?? 80);
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($ip, $port > 0 ? $port : 80, $errno, $errstr, $timeout);
        $success = $socket !== false;

        if ($success) {
            fclose($socket);
        }

        $this->updateConnectionStatus($success ? 'online' : 'offline');

        return $success;
    }

    /**
     * Indique si le terminal est actuellement en ligne.
     */
    public function isOnline(): bool
    {
        return strtolower((string) $this->connection_status) === 'online';
    }

    /**
     * Récupère les journaux de synchronisation du terminal.
     *
     * @param int $limit Nombre maximum d'entrées.
     * @return array<int, array<string, mixed>>
     */
    public function getSyncLogs(int $limit = 20): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        $limit = max(1, $limit);

        return $this->fetchAllRaw(
            'SELECT * FROM `terminal_sync_logs`
             WHERE `terminal_id` = :id
             ORDER BY `started_at` DESC
             LIMIT ' . $limit,
            [':id' => (int) $id]
        );
    }

    /**
     * Met à jour le statut de connexion et la date du dernier test.
     */
    private function updateConnectionStatus(string $status): void
    {
        $id = $this->getKey();
        if ($id === null) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE `terminals`
                 SET `connection_status` = :status, `last_connection_test` = NOW()
                 WHERE `id` = :id'
            );
            $stmt->execute([':status' => $status, ':id' => (int) $id]);

            $this->attributes['connection_status']    = $status;
            $this->attributes['last_connection_test'] = date('Y-m-d H:i:s');
        } catch (PDOException $e) {
            $this->handleError($e, __FUNCTION__);
        }
    }

    /**
     * Met à jour le statut de connexion d'un terminal.
     */
    public function setConnectionStatus(int $id, string $status): bool
    {
        return $this->update($id, [
            'connection_status'    => $status,
            'last_connection_test' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Met à jour l'horodatage de la dernière synchronisation.
     */
    public function markSync(int $id): bool
    {
        return $this->update($id, [
            'last_sync' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Retourne la clé de chiffrement de l'application.
     */
    private static function encryptionKey(): string
    {
        if (defined('APP_ENCRYPTION_KEY') && APP_ENCRYPTION_KEY !== '') {
            return hash('sha256', (string) APP_ENCRYPTION_KEY, true);
        }

        // Clé par défaut : à remplacer par une constante APP_ENCRYPTION_KEY en production.
        return hash('sha256', 'attendance_pro_default_encryption_key', true);
    }
}
