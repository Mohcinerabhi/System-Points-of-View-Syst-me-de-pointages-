<?php
/**
 * Gestion des notifications
 * Fichier: helpers/NotificationHelper.php
 */

namespace App\Helpers;

use App\Models\Notification;
use App\Models\Employee;
use App\Models\AttendanceLog;

class NotificationHelper
{
    /**
     * Envoie une notification à un utilisateur ou à tous les managers.
     */
    public static function send(string $type, string $title, ?string $message = null, ?int $userId = null, ?array $data = null): void
    {
        $model = new Notification();
        $model->send($type, $title, $message, $userId, $data);
    }

    /**
     * Vérifie les retards et notifie les managers.
     */
    public static function checkDelays(): void
    {
        $logModel = new AttendanceLog();
        $today = date('Y-m-d');

        $lateLogs = $logModel->fetchAllRaw(
            "SELECT al.*, e.first_name, e.last_name, e.employee_code
             FROM attendance_logs al
             JOIN employees e ON e.id = al.employee_id
             WHERE al.attendance_date = :today
               AND al.type = 'check_in'
               AND al.late_minutes > 0
               AND e.status = :status
             ORDER BY al.created_at DESC",
            [':today' => $today, ':status' => 'active']
        );

        foreach ($lateLogs as $log) {
            $key = ['employee_id' => (int) $log['employee_id'], 'date' => $today, 'type' => Notification::TYPE_DELAY];

            if ((new Notification())->existsRecent(Notification::TYPE_DELAY, null, $key, 720)) {
                continue;
            }

            $message = sprintf(
                '%s %s (%s) a %d minutes de retard aujourd\'hui.',
                $log['first_name'],
                $log['last_name'],
                $log['employee_code'],
                (int) $log['late_minutes']
            );

            self::sendToManagers(
                Notification::TYPE_DELAY,
                'Retard détecté',
                $message,
                ['employee_id' => (int) $log['employee_id'], 'late_minutes' => (int) $log['late_minutes']]
            );
        }
    }

    /**
     * Vérifie les employés qui n'ont pas pointé aujourd'hui.
     */
    public static function checkMissingPunches(): void
    {
        $today = date('Y-m-d');
        $now = date('H:i:s');

        $employees = (new Employee())->fetchAllRaw(
            "SELECT e.id, e.first_name, e.last_name, e.employee_code
             FROM employees e
             WHERE e.status = 'active'
               AND NOT EXISTS (
                   SELECT 1 FROM attendance_logs al
                   WHERE al.employee_id = e.id
                     AND al.attendance_date = :today
               )
             ORDER BY e.last_name ASC",
            [':today' => $today]
        );

        foreach ($employees as $emp) {
            $key = ['employee_id' => (int) $emp['id'], 'date' => $today, 'type' => Notification::TYPE_MISSING_PUNCH];

            if ((new Notification())->existsRecent(Notification::TYPE_MISSING_PUNCH, null, $key, 720)) {
                continue;
            }

            $message = sprintf(
                '%s %s (%s) n\'a effectué aucun pointage aujourd\'hui.',
                $emp['first_name'],
                $emp['last_name'],
                $emp['employee_code']
            );

            self::sendToManagers(
                Notification::TYPE_MISSING_PUNCH,
                'Pointage manquant',
                $message,
                ['employee_id' => (int) $emp['id']]
            );
        }
    }

    /**
     * Vérifie les heures supplémentaires et notifie.
     */
    public static function checkOvertime(): void
    {
        $logModel = new AttendanceLog();
        $today = date('Y-m-d');

        $rows = $logModel->fetchAllRaw(
            "SELECT al.*, e.first_name, e.last_name, e.employee_code
             FROM attendance_logs al
             JOIN employees e ON e.id = al.employee_id
             WHERE al.attendance_date = :today
               AND al.type = 'check_out'
               AND e.status = :status
             ORDER BY al.updated_at DESC",
            [':today' => $today, ':status' => 'active']
        );

        $alreadyNotified = [];

        foreach ($rows as $log) {
            $employeeId = (int) $log['employee_id'];

            if (isset($alreadyNotified[$employeeId])) {
                continue;
            }

            $workMinutes = (int) ($log['work_duration_minutes'] ?? 0);
            if ($workMinutes <= 0) {
                continue;
            }

            $requiredMinutes = 8 * 60;
            if ($workMinutes <= $requiredMinutes) {
                continue;
            }

            $overtimeMinutes = $workMinutes - $requiredMinutes;

            $key = ['employee_id' => $employeeId, 'date' => $today, 'type' => Notification::TYPE_OVERTIME];
            if ((new Notification())->existsRecent(Notification::TYPE_OVERTIME, null, $key, 720)) {
                $alreadyNotified[$employeeId] = true;
                continue;
            }

            $hours = (int) floor($overtimeMinutes / 60);
            $mins = $overtimeMinutes % 60;
            $overtimeText = $hours > 0 && $mins > 0 ? "{$hours}h {$mins}min" : ($hours > 0 ? "{$hours}h" : "{$mins}min");

            $message = sprintf(
                '%s %s (%s) a effectué %s d\'heures supplémentaires aujourd\'hui.',
                $log['first_name'],
                $log['last_name'],
                $log['employee_code'],
                $overtimeText
            );

            self::sendToManagers(
                Notification::TYPE_OVERTIME,
                'Heures supplémentaires',
                $message,
                ['employee_id' => $employeeId, 'overtime_minutes' => $overtimeMinutes]
            );

            $alreadyNotified[$employeeId] = true;
        }
    }

    /**
     * Exécute toutes les vérifications automatiques.
     */
    public static function runChecks(): void
    {
        self::checkDelays();
        self::checkMissingPunches();
        self::checkOvertime();
    }

    /**
     * Envoie une notification à tous les managers.
     */
    public static function sendToManagers(string $type, string $title, string $message, array $data = []): void
    {
        $managers = (new \App\Models\Notification())->getManagers();

        foreach ($managers as $manager) {
            self::send($type, $title, $message, (int) $manager['id'], $data);
        }
    }
}
