<?php

declare(strict_types=1);

namespace Attendance\Services;

use Attendance\Services\HikvisionClient;
use Attendance\Services\AttendanceSync;
use Attendance\Services\ISAPIClient;
use App\Models\Terminal;
use App\Services\HikvisionTerminal;
use DateTime;

/**
 * Terminal management service.
 *
 * Handles CRUD operations for attendance terminals and connection testing via ISUPClient.
 *
 * @package Attendance\Services
 */
class TerminalService
{
    /** @var \PDO Database connection instance */
    private \PDO $db;

    /** @var string Database table name for terminals */
    private const TABLE = 'terminals';

    /** @var string Database table name for sync logs */
    private const SYNC_LOG_TABLE = 'terminal_sync_logs';

    /**
     * TerminalService constructor.
     *
     * @param \PDO $db Active database connection
     */
    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Returns all terminals.
     *
     * @return array<int, array<string, mixed>> Array of terminal records
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->db->query('SELECT * FROM ' . self::TABLE . ' ORDER BY name ASC');
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Returns a terminal by ID.
     *
     * @param int $id Terminal ID
     * @return array<string, mixed>|null Terminal record or null if not found
     */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Returns all active terminals.
     *
     * @return array<int, array<string, mixed>> Array of active terminal records
     */
    public function getActiveTerminals(): array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE status = :status ORDER BY name ASC');
            $stmt->execute(['status' => 'active']);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Creates a new terminal record.
     *
     * @param array<string, mixed> $terminalData Terminal data including name, ip_address, port, username, password_hash, etc.
     * @return array<string, mixed> Result with 'success' and 'id' keys
     */
    public function create(array $terminalData): array
    {
        try {
            $sql = 'INSERT INTO ' . self::TABLE . '
                (name, ip_address, port, username, password_hash, serial_number, model,
                connection_status, last_connection_test, last_sync, sync_enabled, status, notes)
                VALUES
                (:name, :ip_address, :port, :username, :password_hash, :serial_number, :model,
                :connection_status, :last_connection_test, :last_sync, :sync_enabled, :status, :notes)';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $terminalData['name'] ?? '',
                'ip_address' => $terminalData['ip_address'] ?? '',
                'port' => $terminalData['port'] ?? 80,
                'username' => $terminalData['username'] ?? '',
                'password_hash' => $terminalData['password_hash'] ?? '',
                'serial_number' => $terminalData['serial_number'] ?? null,
                'model' => $terminalData['model'] ?? null,
                'connection_status' => $terminalData['connection_status'] ?? 'offline',
                'last_connection_test' => $terminalData['last_connection_test'] ?? null,
                'last_sync' => $terminalData['last_sync'] ?? null,
                'sync_enabled' => $terminalData['sync_enabled'] ?? 1,
                'status' => $terminalData['status'] ?? 'active',
                'notes' => $terminalData['notes'] ?? null,
            ]);

            return [
                'success' => true,
                'id' => (int)$this->db->lastInsertId(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Updates a terminal record.
     *
     * @param int   $id            Terminal ID
     * @param array $terminalData  Fields to update
     * @return array<string, mixed> Result with 'success' key
     */
    public function update(int $id, array $terminalData): array
    {
        try {
            $allowedFields = [
                'name', 'ip_address', 'port', 'username', 'password_hash', 'serial_number',
                'model', 'connection_status', 'last_connection_test', 'last_sync',
                'sync_enabled', 'status', 'notes',
            ];

            $updates = [];
            $params = ['id' => $id];

            foreach ($terminalData as $field => $value) {
                if (in_array($field, $allowedFields, true)) {
                    $updates[] = "{$field} = :{$field}";
                    $params[$field] = $value;
                }
            }

            if (empty($updates)) {
                return ['success' => false, 'error' => 'No valid fields to update'];
            }

            $sql = 'UPDATE ' . self::TABLE . ' SET ' . implode(', ', $updates) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return ['success' => true, 'affected_rows' => $stmt->rowCount()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deletes a terminal record.
     *
     * @param int $id Terminal ID
     * @return array<string, mixed> Result with 'success' key
     */
    public function delete(int $id): array
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM ' . self::TABLE . ' WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return ['success' => true, 'affected_rows' => $stmt->rowCount()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tests connection to a specific terminal using HikvisionTerminal.
     *
     * Updates the terminal's connection_status and last_connection_test in the database.
     *
     * @param int $id Terminal ID
     * @return array<string, mixed> Test result from client::testConnection()
     */
    public function testConnection(int $id): array
    {
        $terminal = $this->getById($id);
        if (!$terminal) {
            return ['success' => false, 'error' => 'Terminal not found'];
        }

        $password = Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);
        $status = $client->testConnection();

        $newStatus = $status['authenticated'] ? 'online' : ($status['reachable'] ? 'error' : 'offline');

        \App\Helpers\Logger::info("TerminalService testConnection", [
            'terminal_id' => $id,
            'terminal_name' => $terminal['name'] ?? '',
            'new_status' => $newStatus,
            'http_code' => $status['http_code'] ?? 0,
            'authenticated' => $status['authenticated'] ?? false,
            'reachable' => $status['reachable'] ?? false,
            'error' => $status['error'] ?? null,
        ]);

        $this->update($id, [
            'connection_status' => $newStatus,
            'last_connection_test' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return $status;
    }

    /**
     * Updates terminal connection status.
     *
     * @param int    $id      Terminal ID
     * @param string $status  One of: online, offline, error
     * @return array<string, mixed>
     */
    public function updateConnectionStatus(int $id, string $status): array
    {
        $allowed = ['online', 'offline', 'error'];
        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid status'];
        }

        return $this->update($id, [
            'connection_status' => $status,
            'last_connection_test' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Syncs all active terminals.
     *
     * Iterates over all active terminals with sync_enabled = 1 and triggers a sync.
     * TODO: Implement actual sync logic using AttendanceSync service.
     *
     * @return array<string, mixed> Summary of sync results per terminal
     */
    public function syncAllTerminals(): array
    {
        $terminals = $this->getActiveTerminals();
        $results = [];

        foreach ($terminals as $terminal) {
            if ((int)$terminal['sync_enabled'] !== 1) {
                continue;
            }

            $password = Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
            $isapiClient = new ISAPIClient(
                (string) ($terminal['ip_address'] ?? ''),
                (int) ($terminal['port'] ?? 80),
                (string) ($terminal['username'] ?? ''),
                (string) ($password),
                10,
                !empty($terminal['use_https']),
                $terminal['timezone'] ?? '+00:00'
            );

            $attendanceSync = new AttendanceSync($this->db, $isapiClient, null);
            $terminalResult = $attendanceSync->syncFromTerminal((int)$terminal['id'], $terminal['timezone'] ?? null);

            $results[] = [
                'terminal_id' => $terminal['id'],
                'name' => $terminal['name'],
                'status' => $terminalResult['success'] ? 'success' : 'error',
                'message' => $terminalResult['message'] ?? ($terminalResult['error'] ?? 'Unknown'),
                'records_synced' => $terminalResult['records_synced'] ?? 0,
                'error' => $terminalResult['error'] ?? null,
                'endpoint' => $terminalResult['endpoint'] ?? null,
                'http_code' => $terminalResult['http_code'] ?? 0,
            ];
        }

        return ['success' => true, 'results' => $results];
    }

    /**
     * Returns sync logs for a terminal.
     *
     * @param int|null $terminalId Terminal ID (null for all terminals)
     * @param int      $limit      Maximum number of records to return (default 50)
     * @return array<int, array<string, mixed>> Sync log records
     */
    public function getSyncLogs(?int $terminalId = null, int $limit = 50): array
    {
        try {
            $sql = 'SELECT * FROM ' . self::SYNC_LOG_TABLE;
            $params = [];

            if ($terminalId !== null) {
                $sql .= ' WHERE terminal_id = :terminal_id';
                $params['terminal_id'] = $terminalId;
            }

            $sql .= ' ORDER BY started_at DESC LIMIT :limit';
            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
