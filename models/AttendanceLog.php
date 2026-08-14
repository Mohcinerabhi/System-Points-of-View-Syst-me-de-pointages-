<?php
/**
 * Modèle Pointage (table `attendance_logs`)
 * Fichier: models/AttendanceLog.php
 *
 * @property int         $id
 * @property int         $employee_id
 * @property string      $attendance_date
 * @property string      $attendance_time
 * @property string      $type
 * @property int|null    $terminal_id
 * @property string      $terminal_ip
 * @property string      $source
 * @property int|null    $work_duration_minutes
 * @property int         $late_minutes
 * @property int         $early_departure_minutes
 * @property int         $overtime_minutes
 * @property int         $missing_minutes
 * @property string|null $notes
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class AttendanceLog extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'attendance_logs';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'employee_id',
        'attendance_date',
        'attendance_time',
        'type',
        'terminal_id',
        'terminal_ip',
        'source',
        'work_duration_minutes',
        'late_minutes',
        'early_departure_minutes',
        'overtime_minutes',
        'missing_minutes',
        'notes',
    ];

    /**
     * Récupère l'employé associé au pointage.
     */
    public function getEmployee(): ?Employee
    {
        $employeeId = $this->employee_id;
        if ($employeeId === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT * FROM `employees` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $employeeId]
        );

        if ($row === null) {
            return null;
        }

        $employee = new Employee();
        $employee->fill($row);

        return $employee;
    }

    /**
     * Récupère le terminal associé au pointage.
     */
    public function getTerminal(): ?Terminal
    {
        $terminalId = $this->terminal_id;
        if ($terminalId === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT * FROM `terminals` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $terminalId]
        );

        if ($row === null) {
            return null;
        }

        $terminal = new Terminal();
        $terminal->fill($row);

        return $terminal;
    }

    /**
     * Indique si le pointage présente un retard.
     */
    public function isLate(): bool
    {
        return (int) $this->late_minutes > 0;
    }

    /**
     * Indique si le pointage correspond à un départ anticipé.
     */
    public function isEarlyDeparture(): bool
    {
        return (int) $this->early_departure_minutes > 0;
    }

    /**
     * Indique si le pointage comporte des heures supplémentaires.
     */
    public function isOvertime(): bool
    {
        return (int) $this->overtime_minutes > 0;
    }

    /**
     * Retourne l'heure de pointage formatée.
     *
     * @param string $format Format de sortie (défaut: H:i).
     */
    public function getFormattedTime(string $format = 'H:i'): string
    {
        $time = (string) ($this->attendance_time ?? '');
        if ($time === '') {
            return '';
        }

        $timestamp = strtotime($time);

        return $timestamp !== false ? date($format, $timestamp) : $time;
    }

    /**
     * Statistiques de présence pour une date donnée.
     *
     * @param string $date Date (Y-m-d).
     * @return array<string, int>
     */
    public function statsForDate(string $date): array
    {
        $checkins = $this->fetchOneRaw(
            "SELECT COUNT(DISTINCT `employee_id`) AS present,
                    COUNT(*) AS total_checkins
             FROM `attendance_logs`
             WHERE `attendance_date` = :date AND `type` = 'check_in'",
            [':date' => $date]
        );

        $late = $this->fetchOneRaw(
            "SELECT COUNT(DISTINCT `employee_id`) AS late
             FROM `attendance_logs`
             WHERE `attendance_date` = :date AND `late_minutes` > 0",
            [':date' => $date]
        );

        $early = $this->fetchOneRaw(
            "SELECT COUNT(DISTINCT `employee_id`) AS early
             FROM `attendance_logs`
             WHERE `attendance_date` = :date AND `early_departure_minutes` > 0",
            [':date' => $date]
        );

        $current = $this->fetchOneRaw(
            "SELECT COUNT(DISTINCT `employee_id`) AS currently_present
             FROM `attendance_logs` al
             WHERE al.`attendance_date` = :date AND al.`type` = 'check_in'
               AND NOT EXISTS (
                 SELECT 1 FROM `attendance_logs` o
                 WHERE o.`employee_id` = al.`employee_id`
                   AND o.`attendance_date` = al.`attendance_date`
                   AND o.`type` = 'check_out'
               )",
            [':date' => $date]
        );

        return [
            'present_today'     => (int) ($checkins['present'] ?? 0),
            'total_checkins'    => (int) ($checkins['total_checkins'] ?? 0),
            'late'              => (int) ($late['late'] ?? 0),
            'early_departures'  => (int) ($early['early'] ?? 0),
            'currently_present' => (int) ($current['currently_present'] ?? 0),
            'total'             => (int) ($checkins['total_checkins'] ?? 0),
        ];
    }

    /**
     * Compte le nombre d'employés absents pour une date donnée.
     *
     * Un employé est considéré absent s'il est actif mais sans pointage
     * d'entrée (`check_in`) ce jour-là.
     */
    public function absentCount(string $date): int
    {
        $present = $this->fetchOneRaw(
            "SELECT COUNT(DISTINCT `employee_id`) AS present
             FROM `attendance_logs`
             WHERE `attendance_date` = :date AND `type` = 'check_in'",
            [':date' => $date]
        );
        $presentCount = (int) ($present['present'] ?? 0);

        $row  = $this->fetchOneRaw("SELECT COUNT(*) AS total FROM `employees` WHERE `status` = 'active'");
        $total = (int) ($row['total'] ?? 0);

        return max(0, $total - $presentCount);
    }

    /**
     * Récupère les pointages les plus récents avec les informations employé.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecent(int $limit = 15): array
    {
        $limit = max(1, $limit);

        return $this->fetchAllRaw(
            "SELECT al.*, e.`first_name`, e.`last_name`, e.`photo`,
                    d.`name` AS department_name
             FROM `attendance_logs` al
             LEFT JOIN `employees` e ON e.`id` = al.`employee_id`
             LEFT JOIN `departments` d ON d.`id` = e.`department_id`
             ORDER BY al.`attendance_date` DESC, al.`attendance_time` DESC
             LIMIT {$limit}"
        );
    }

    /**
     * Données de présence mensuelle pour le graphique (12 mois).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyChart(int $year): array
    {
        $rows = $this->fetchAllRaw(
            "SELECT MONTH(`attendance_date`) AS month,
                    COUNT(DISTINCT `attendance_date`) AS days,
                    COUNT(DISTINCT CASE WHEN `late_minutes` > 0 THEN `employee_id` END) AS late
             FROM `attendance_logs`
             WHERE YEAR(`attendance_date`) = :year AND `type` = 'check_in'
             GROUP BY MONTH(`attendance_date`)",
            [':year' => (int) $year]
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['month']] = [
                'days' => (int) $r['days'],
                'late' => (int) $r['late'],
            ];
        }

        $months = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[] = [
                'month' => $m,
                'label' => $months[$m - 1],
                'days'  => $map[$m]['days'] ?? 0,
                'late'  => $map[$m]['late'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Données calendrier (agrégées par jour) pour un mois donné.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarData(int $year, int $month): array
    {
        return $this->fetchAllRaw(
            "SELECT `attendance_date`,
                    COUNT(DISTINCT `employee_id`) AS employees,
                    SUM(CASE WHEN `late_minutes` > 0 THEN 1 ELSE 0 END) AS late_minutes
             FROM `attendance_logs`
             WHERE YEAR(`attendance_date`) = :year
               AND MONTH(`attendance_date`) = :month
               AND `type` = 'check_in'
             GROUP BY `attendance_date`
             ORDER BY `attendance_date` ASC",
            [':year' => (int) $year, ':month' => (int) $month]
        );
    }

    /**
     * Récupère les pointages groupés par employé et date avec heures d'entrée/sortie
     * et durée de travail calculée.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailySummaries(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'al.attendance_date = :date';
            $params[':date'] = $filters['date'];
        }
        if (!empty($filters['employee_id'])) {
            $where[] = 'al.employee_id = :employee_id';
            $params[':employee_id'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['department_id'])) {
            $where[] = 'e.department_id = :department_id';
            $params[':department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['type'])) {
            $where[] = 'al.type = :type';
            $params[':type'] = $filters['type'];
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(DISTINCT al.employee_id, al.attendance_date) FROM attendance_logs al JOIN employees e ON e.id = al.employee_id LEFT JOIN departments d ON d.id = e.department_id{$sqlWhere}";
        $countStmt = $this->db()->prepare($countSql);
        foreach ($params as $key => $val) {
            $countStmt->bindValue($key, $val);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT MAX(al.id) AS id,
                       al.employee_id,
                       al.attendance_date,
                       MIN(CASE WHEN al.type = 'check_in' THEN al.attendance_time END) AS entry_time,
                       MIN(CASE WHEN al.type = 'break_start' THEN al.attendance_time END) AS break_start_time,
                       MIN(CASE WHEN al.type = 'break_end' THEN al.attendance_time END) AS break_end_time,
                       MAX(CASE WHEN al.type = 'check_out' THEN al.attendance_time END) AS exit_time,
                       SUM(CASE WHEN al.type IN ('check_in', 'check_out', 'break_start', 'break_end') THEN 1 ELSE 0 END) AS events_count,
                       MAX(al.work_duration_minutes) AS work_duration_minutes,
                       MAX(al.late_minutes) AS late_minutes,
                       MAX(al.early_departure_minutes) AS early_departure_minutes,
                       e.first_name,
                       e.last_name,
                       e.employee_code,
                       e.photo,
                       e.badge_id,
                       d.name AS department_name
                FROM attendance_logs al
                JOIN employees e ON e.id = al.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                {$sqlWhere}
                GROUP BY al.employee_id, al.attendance_date
                ORDER BY al.attendance_date DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $workMinutes = (int) ($row['work_duration_minutes'] ?? 0);
            if ($workMinutes <= 0 && !empty($row['entry_time']) && !empty($row['exit_time'])) {
                $workMinutes = $this->calculateWorkDuration($row['entry_time'], $row['exit_time'], $row['break_start_time'] ?? null, $row['break_end_time'] ?? null);
            }

            $row['work_duration_minutes'] = $workMinutes;
            $row['work_duration_human'] = $this->formatWorkDuration($workMinutes);

            $row['overtime_minutes'] = $this->calculateOvertime($workMinutes);
            $row['overtime_human'] = $this->formatWorkDuration($row['overtime_minutes']);

            [$row['work_status'], $row['work_status_detail']] = $this->calculateWorkStatus($row, $workMinutes);

            $result[] = $row;
        }

        return [
            'data' => $result,
            'total' => $total,
        ];
    }

    /**
     * Calcule le statut de la journée de travail.
     *
     * @return array{0: string, 1: string} [status, detail]
     */
    private function calculateWorkStatus(array $row, int $workMinutes): array
    {
        $requiredMinutes = 8 * 60; // 8 heures

        $hasEntry = !empty($row['entry_time']);
        $hasExit = !empty($row['exit_time']);

        if ($hasEntry && !$hasExit) {
            $entryTs = strtotime($row['entry_time']);
            $nowTs = time();
            $elapsedMinutes = (int) ceil(($nowTs - $entryTs) / 60);

            if ($elapsedMinutes >= $requiredMinutes) {
                return ['Journée complète', 'Objectif atteint'];
            }

            $remaining = $requiredMinutes - $elapsedMinutes;
            return ['En cours', $this->formatWorkDuration($remaining) . ' restantes'];
        }

        if ($workMinutes >= $requiredMinutes) {
            return ['Journée complète', 'Objectif atteint'];
        }

        $missing = $requiredMinutes - $workMinutes;
        return ['Temps insuffisant', $this->formatWorkDuration($missing) . ' manquantes'];
    }

    /**
     * Calcule la durée de travail en minutes entre l'entrée et la sortie,
     * en soustrayant la pause si elle existe.
     */
    private function calculateWorkDuration(?string $entry, ?string $exit, ?string $breakStart, ?string $breakEnd): int
    {
        if (empty($entry) || empty($exit)) {
            return 0;
        }

        $entryTs = strtotime($entry);
        $exitTs = strtotime($exit);

        if ($entryTs === false || $exitTs === false || $exitTs <= $entryTs) {
            return 0;
        }

        $totalMinutes = (int) ceil(($exitTs - $entryTs) / 60);

        if (!empty($breakStart) && !empty($breakEnd)) {
            $breakStartTs = strtotime($breakStart);
            $breakEndTs = strtotime($breakEnd);
            if ($breakStartTs !== false && $breakEndTs !== false && $breakEndTs > $breakStartTs) {
                $totalMinutes -= (int) ceil(($breakEndTs - $breakStartTs) / 60);
            }
        }

        return max(0, $totalMinutes);
    }

    /**
     * Formate une durée en minutes en texte lisible.
     */
    private function formatWorkDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '-';
        }

        $hours = (int) floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}min";
        }

        if ($hours > 0) {
            return "{$hours}h";
        }

        return "{$mins}min";
    }

    /**
     * Calcule les heures supplémentaires selon la durée de travail.
     *
     * @return int Minutes supplémentaires (0 si <= 8h)
     */
    private function calculateOvertime(int $workMinutes): int
    {
        $requiredMinutes = 8 * 60; // 8 heures

        if ($workMinutes > $requiredMinutes) {
            return $workMinutes - $requiredMinutes;
        }

        return 0;
    }

    /**
     * Données de présence quotidienne pour les N derniers jours.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDailyPresence(int $days = 7): array
    {
        $rows = $this->fetchAllRaw(
            "SELECT DATE(`attendance_date`) AS date,
                    COUNT(DISTINCT `employee_id`) AS present,
                    COUNT(DISTINCT CASE WHEN `late_minutes` > 0 THEN `employee_id` END) AS late,
                    COUNT(DISTINCT CASE WHEN `early_departure_minutes` > 0 THEN `employee_id` END) AS early
             FROM `attendance_logs`
             WHERE `attendance_date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
               AND `type` = 'check_in'
             GROUP BY DATE(`attendance_date`)
             ORDER BY DATE(`attendance_date`) ASC",
            [':days' => max(1, $days)]
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['date']] = [
                'present' => (int) $r['present'],
                'late' => (int) $r['late'],
                'early' => (int) $r['early'],
            ];
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $entry = $map[$date] ?? ['present' => 0, 'late' => 0, 'early' => 0];
            $result[] = [
                'label' => date('d/m', strtotime($date)),
                'present' => $entry['present'],
                'late' => $entry['late'],
                'early' => $entry['early'],
            ];
        }

        return $result;
    }

    public function getDailyDelays(int $days = 7): array
    {
        $rows = $this->fetchAllRaw(
            "SELECT DATE(`attendance_date`) AS date,
                    COUNT(DISTINCT `employee_id`) AS late_count,
                    SUM(`late_minutes`) AS total_minutes
             FROM `attendance_logs`
             WHERE `attendance_date` >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
               AND `late_minutes` > 0
             GROUP BY DATE(`attendance_date`)
             ORDER BY DATE(`attendance_date`) ASC",
            [':days' => max(1, $days)]
        );

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['date']] = [
                'late' => (int) $r['late_count'],
                'minutes' => (int) $r['total_minutes'],
            ];
        }

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $entry = $map[$date] ?? ['late' => 0, 'minutes' => 0];
            $result[] = [
                'label' => date('d/m', strtotime($date)),
                'late' => $entry['late'],
                'minutes' => $entry['minutes'],
            ];
        }

        return $result;
    }
}
