<?php
namespace App\Models;

/**
 * Modèle Journal de synchronisation terminal (terminal_sync_logs).
 */
class TerminalSyncLog extends BaseModel
{
    protected string $table = 'terminal_sync_logs';
    protected string $primaryKey = 'id';
    protected array $fillable = [
        'terminal_id', 'sync_type', 'status', 'records_synced',
        'records_failed', 'message', 'started_at', 'finished_at',
    ];

    public function getByTerminal(int $terminalId, int $limit = 50): array
    {
        $sql = "SELECT * FROM terminal_sync_logs WHERE terminal_id = ? ORDER BY started_at DESC LIMIT ?";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(1, $terminalId, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
