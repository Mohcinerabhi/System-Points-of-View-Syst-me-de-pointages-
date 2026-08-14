<?php
namespace App\Helpers;

use App\Models\AuditLog;

/**
 * Enregistrement des actions dans les journaux d'audit.
 */
class AuditLogger
{
    public static function log(
        string $action,
        string $module,
        ?string $description = null,
        ?int $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $user = Auth::user();
            $data = [
                'user_id'    => Auth::id(),
                'user_name'  => Auth::name(),
                'user_role'  => Auth::role(),
                'action'     => $action,
                'module'     => $module,
                'description'=> $description,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'record_id'  => $recordId,
                'old_values' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
            ];
            (new AuditLog())->create($data);
        } catch (\Throwable $e) {
            // Ne jamais faire échouer l'application à cause d'un log d'audit.
            error_log('AuditLogger error: ' . $e->getMessage());
        }
    }
}
